<?php

namespace App\Services\WhatsApp;

class WhatsAppService
{
    private $provider;
    private $config;

    public function __construct()
    {
        $this->config = [
            'api_url' => env('BOTCONVERSA_API_URL'),
            'token' => env('BOTCONVERSA_TOKEN'),
            'webhook_secret' => env('BOTCONVERSA_WEBHOOK_SECRET')
        ];
        
        $this->provider = new BotConversaProvider($this->config);
    }

    public function handleWebhook($request)
    {
        // Verify webhook signature
        if (!$this->verifyWebhookSignature($request)) {
            throw new \Exception('Invalid webhook signature');
        }
        
        $data = json_decode($request->getContent(), true);
        
        // Check if message contains image
        if ($this->isImageMessage($data)) {
            return $this->processImageMessage($data);
        }
        
        // Handle text commands
        return $this->handleTextCommand($data);
    }

    public function sendQuote($phoneNumber, $quote, $paymentLink)
    {
        $message = $this->formatQuoteMessage($quote, $paymentLink);
        
        return $this->provider->sendMessage([
            'to' => $this->formatPhoneNumber($phoneNumber),
            'type' => 'text',
            'text' => $message,
            'buttons' => [
                [
                    'type' => 'url',
                    'text' => '💳 Pagar Online',
                    'url' => $paymentLink
                ],
                [
                    'type' => 'reply',
                    'text' => '📞 Falar com Atendente'
                ]
            ]
        ]);
    }

    private function formatQuoteMessage($quote, $paymentLink)
    {
        $items = array_map(function($item) {
            return sprintf(
                "• %s - %s\n  Quantidade: %d | Valor: R$ %.2f",
                $item['medication'],
                $item['dosage'],
                $item['quantity'],
                $item['price']
            );
        }, $quote['items']);
        
        $message = sprintf(
            "🏥 *Orçamento - Fórmula Certa*\n\n" .
            "📋 Prescrição: #%s\n" .
            "👤 Paciente: %s\n" .
            "📅 Data: %s\n\n" .
            "*Medicamentos:*\n%s\n\n" .
            "💰 *Total: R$ %.2f*\n\n" .
            "✅ Clique no botão abaixo para pagar online\n" .
            "📍 Ou retire na loja com este código: %s",
            $quote['prescription_id'],
            $quote['patient_name'],
            date('d/m/Y H:i'),
            implode("\n", $items),
            $quote['total'],
            $quote['pickup_code']
        );
        
        return $message;
    }

    private function isImageMessage($data)
    {
        return isset($data['message']['type']) && 
               $data['message']['type'] === 'image';
    }

    private function processImageMessage($data)
    {
        $imageUrl = $data['message']['image']['url'];
        $phoneNumber = $data['from'];
        
        // Queue for processing
        $jobId = $this->queuePrescriptionProcessing($imageUrl, $phoneNumber);
        
        // Send acknowledgment
        $this->provider->sendMessage([
            'to' => $phoneNumber,
            'type' => 'text',
            'text' => "📸 Recebi sua prescrição!\n\n" .
                     "🔄 Estou analisando a imagem...\n" .
                     "⏱️ Você receberá o orçamento em alguns instantes."
        ]);
        
        return ['job_id' => $jobId];
    }

    private function handleTextCommand($data)
    {
        $text = strtolower(trim($data['message']['text']));
        $phoneNumber = $data['from'];
        
        switch ($text) {
            case 'status':
                return $this->sendStatusUpdate($phoneNumber);
                
            case 'ajuda':
            case 'help':
                return $this->sendHelpMessage($phoneNumber);
                
            case 'cancelar':
                return $this->cancelPendingOrders($phoneNumber);
                
            default:
                return $this->sendDefaultMessage($phoneNumber);
        }
    }

    private function verifyWebhookSignature($request)
    {
        $signature = $request->header('X-Webhook-Signature');
        $payload = $request->getContent();
        
        $expectedSignature = hash_hmac('sha256', $payload, $this->config['webhook_secret']);
        
        return hash_equals($expectedSignature, $signature);
    }

    private function formatPhoneNumber($phone)
    {
        // Ensure Brazilian format: 5511999999999
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($phone) === 11) {
            $phone = '55' . $phone;
        }
        
        return $phone;
    }

    private function queuePrescriptionProcessing($imageUrl, $phoneNumber)
    {
        // Add to Redis queue
        $jobData = [
            'image_url' => $imageUrl,
            'phone_number' => $phoneNumber,
            'timestamp' => time()
        ];
        
        $jobId = uniqid('rx_', true);
        
        // Queue job
        app('redis')->rpush('prescription_queue', json_encode([
            'id' => $jobId,
            'data' => $jobData
        ]));
        
        return $jobId;
    }

    private function sendHelpMessage($phoneNumber)
    {
        $this->provider->sendMessage([
            'to' => $phoneNumber,
            'type' => 'text',
            'text' => "🏥 *Fórmula Certa - Como usar*\n\n" .
                     "📸 Envie uma foto da sua receita médica\n" .
                     "⏱️ Aguarde o processamento (1-2 minutos)\n" .
                     "💰 Receba o orçamento com link de pagamento\n\n" .
                     "*Comandos disponíveis:*\n" .
                     "• STATUS - Ver pedidos pendentes\n" .
                     "• AJUDA - Mostrar esta mensagem\n" .
                     "• CANCELAR - Cancelar pedidos\n\n" .
                     "Dúvidas? Digite ATENDENTE"
        ]);
    }
}