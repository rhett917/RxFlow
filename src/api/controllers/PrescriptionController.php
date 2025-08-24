<?php

namespace App\Api\Controllers;

use App\Services\OCR\OCRService;
use App\Services\Validation\PrescriptionValidator;
use App\Services\ERP\FormulaCartaService;
use App\Services\WhatsApp\WhatsAppService;
use App\Services\Payment\PaymentService;

class PrescriptionController
{
    private $ocrService;
    private $validator;
    private $erpService;
    private $whatsappService;
    private $paymentService;

    public function __construct()
    {
        $this->ocrService = new OCRService();
        $this->validator = new PrescriptionValidator();
        $this->erpService = new FormulaCartaService();
        $this->whatsappService = new WhatsAppService();
        $this->paymentService = new PaymentService();
    }

    public function processPrescription($request)
    {
        try {
            // 1. Extract text from image
            $imageData = $request->getImageData();
            $ocrResult = $this->ocrService->extractText($imageData);
            
            // 2. Validate and structure data
            $prescriptionData = $this->validator->validate($ocrResult);
            
            // 3. Check confidence level
            if ($prescriptionData['confidence'] < 0.85) {
                return $this->requestManualValidation($prescriptionData);
            }
            
            // 4. Insert into ERP
            $erpResponse = $this->erpService->createPrescription($prescriptionData);
            
            // 5. Generate quote
            $quote = $this->erpService->generateQuote($erpResponse['prescription_id']);
            
            // 6. Create payment link
            $paymentLink = $this->paymentService->createPaymentLink($quote);
            
            // 7. Send WhatsApp message
            $this->whatsappService->sendQuote(
                $request->getPhoneNumber(),
                $quote,
                $paymentLink
            );
            
            return [
                'success' => true,
                'prescription_id' => $erpResponse['prescription_id'],
                'quote_id' => $quote['id']
            ];
            
        } catch (\Exception $e) {
            $this->logError($e);
            return [
                'success' => false,
                'error' => 'Failed to process prescription',
                'message' => $e->getMessage()
            ];
        }
    }

    private function requestManualValidation($prescriptionData)
    {
        // Queue for manual review
        return [
            'success' => true,
            'requires_validation' => true,
            'validation_id' => $this->validator->queueForReview($prescriptionData)
        ];
    }

    private function logError($exception)
    {
        error_log(sprintf(
            "[%s] Prescription processing error: %s\n%s",
            date('Y-m-d H:i:s'),
            $exception->getMessage(),
            $exception->getTraceAsString()
        ));
    }
}