<?php

namespace App\Services\OCR;

class OCRService
{
    private $service;
    private $preprocessor;

    public function __construct()
    {
        $ocrType = env('OCR_SERVICE', 'tesseract');
        
        switch ($ocrType) {
            case 'google_vision':
                $this->service = new GoogleVisionOCR();
                break;
            case 'tesseract':
            default:
                $this->service = new TesseractOCR();
                break;
        }
        
        $this->preprocessor = new ImagePreprocessor();
    }

    public function extractText($imageData)
    {
        // Preprocess image for better OCR results
        $processedImage = $this->preprocessor->process($imageData);
        
        // Extract text
        $rawText = $this->service->recognize($processedImage);
        
        // Parse prescription format
        $parsedData = $this->parsePrescription($rawText);
        
        return [
            'raw_text' => $rawText,
            'parsed_data' => $parsedData,
            'confidence' => $this->service->getConfidence(),
            'is_handwritten' => $this->detectHandwriting($processedImage)
        ];
    }

    private function parsePrescription($text)
    {
        $patterns = [
            'patient_name' => '/(?:Paciente|Nome):\s*(.+?)(?:\n|$)/i',
            'doctor_name' => '/(?:Dr\.?|Dra\.?)\s*(.+?)(?:\n|CRM)/i',
            'crm' => '/CRM[:\s]*(\d+)/i',
            'date' => '/(?:Data):\s*(\d{1,2}\/\d{1,2}\/\d{2,4})/i',
            'medications' => '/(?:Medicamento|Medicação):\s*(.+?)(?:Posologia|$)/si'
        ];
        
        $data = [];
        foreach ($patterns as $field => $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $data[$field] = trim($matches[1]);
            }
        }
        
        // Extract medications list
        $data['medications'] = $this->extractMedications($text);
        
        return $data;
    }

    private function extractMedications($text)
    {
        $medications = [];
        
        // Pattern for medication lines
        $lines = explode("\n", $text);
        $inMedSection = false;
        
        foreach ($lines as $line) {
            if (preg_match('/(?:Medicamento|Medicação|Prescrição)/i', $line)) {
                $inMedSection = true;
                continue;
            }
            
            if ($inMedSection && trim($line)) {
                // Parse medication details
                $medication = $this->parseMedicationLine($line);
                if ($medication) {
                    $medications[] = $medication;
                }
            }
        }
        
        return $medications;
    }

    private function parseMedicationLine($line)
    {
        // Pattern: Medication name - Dosage - Frequency
        if (preg_match('/(.+?)\s*[-–]\s*(\d+\s*mg|\d+\s*ml)\s*[-–]\s*(.+)/', $line, $matches)) {
            return [
                'name' => trim($matches[1]),
                'dosage' => trim($matches[2]),
                'frequency' => trim($matches[3])
            ];
        }
        
        return null;
    }

    private function detectHandwriting($image)
    {
        // Simple heuristic - can be enhanced with ML
        $confidence = $this->service->getConfidence();
        return $confidence < 0.7;
    }
}