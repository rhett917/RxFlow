<?php

namespace App\Services\Validation;

use Exception;

class PrescriptionValidator
{
    private $rScriptPath;
    private $validationScript;
    private $queueService;

    public function __construct()
    {
        $this->rScriptPath = env('R_SCRIPT_PATH', '/usr/bin/Rscript');
        $this->validationScript = base_path('src/services/validation/validate_prescription.R');
        $this->queueService = new ValidationQueueService();
    }

    public function validate($ocrResult)
    {
        try {
            // Prepare data for R script
            $inputData = json_encode([
                'parsed_data' => $ocrResult['parsed_data'],
                'confidence' => $ocrResult['confidence'],
                'is_handwritten' => $ocrResult['is_handwritten']
            ]);

            // Execute R validation script
            $output = $this->executeRScript($inputData);
            
            // Parse R output
            $validationResult = json_decode($output, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Failed to parse R script output: ' . $output);
            }

            // Merge results
            return array_merge($ocrResult['parsed_data'], [
                'validation' => $validationResult,
                'confidence' => $validationResult['confidence'],
                'requires_manual_review' => $this->requiresManualReview($validationResult)
            ]);

        } catch (Exception $e) {
            error_log("Validation error: " . $e->getMessage());
            
            // Fallback to basic validation
            return $this->basicValidation($ocrResult);
        }
    }

    private function executeRScript($inputData)
    {
        // Escape input for shell
        $escapedInput = escapeshellarg($inputData);
        
        // Build command
        $command = sprintf(
            '%s %s %s 2>&1',
            $this->rScriptPath,
            escapeshellarg($this->validationScript),
            $escapedInput
        );
        
        // Execute
        $output = shell_exec($command);
        
        if ($output === null) {
            throw new Exception('Failed to execute R script');
        }
        
        return trim($output);
    }

    private function basicValidation($ocrResult)
    {
        $data = $ocrResult['parsed_data'];
        $errors = [];
        $warnings = [];
        
        // Basic validations
        if (empty($data['patient_name'])) {
            $errors[] = 'Patient name is missing';
        }
        
        if (empty($data['doctor_name']) || empty($data['crm'])) {
            $errors[] = 'Doctor information is incomplete';
        }
        
        if (empty($data['medications'])) {
            $errors[] = 'No medications found';
        }
        
        // Validate each medication
        foreach ($data['medications'] ?? [] as $med) {
            if (empty($med['name'])) {
                $errors[] = 'Medication name is missing';
            }
            
            if (empty($med['dosage']) || !preg_match('/\d+\s*(mg|ml|g)/i', $med['dosage'])) {
                $warnings[] = 'Invalid dosage format for ' . ($med['name'] ?? 'medication');
            }
        }
        
        return array_merge($data, [
            'validation' => [
                'is_valid' => empty($errors),
                'errors' => $errors,
                'warnings' => $warnings,
                'confidence' => $ocrResult['confidence'] * (empty($errors) ? 1 : 0.5)
            ],
            'confidence' => $ocrResult['confidence'] * (empty($errors) ? 1 : 0.5),
            'requires_manual_review' => !empty($errors) || !empty($warnings)
        ]);
    }

    private function requiresManualReview($validationResult)
    {
        return !$validationResult['is_valid'] || 
               $validationResult['confidence'] < 0.85 ||
               !empty($validationResult['warnings']);
    }

    public function queueForReview($prescriptionData)
    {
        return $this->queueService->add([
            'prescription_data' => $prescriptionData,
            'timestamp' => time(),
            'status' => 'pending_review'
        ]);
    }
}

class ValidationQueueService
{
    private $redis;
    
    public function __construct()
    {
        $this->redis = app('redis');
    }
    
    public function add($data)
    {
        $id = uniqid('val_', true);
        $data['id'] = $id;
        
        $this->redis->hset('validation_queue', $id, json_encode($data));
        $this->redis->rpush('validation_pending', $id);
        
        return $id;
    }
    
    public function getPending()
    {
        $ids = $this->redis->lrange('validation_pending', 0, -1);
        $items = [];
        
        foreach ($ids as $id) {
            $data = $this->redis->hget('validation_queue', $id);
            if ($data) {
                $items[] = json_decode($data, true);
            }
        }
        
        return $items;
    }
    
    public function approve($id, $corrections = [])
    {
        $data = $this->redis->hget('validation_queue', $id);
        if (!$data) {
            throw new Exception('Validation item not found');
        }
        
        $item = json_decode($data, true);
        $item['status'] = 'approved';
        $item['corrections'] = $corrections;
        $item['approved_at'] = time();
        
        $this->redis->hset('validation_queue', $id, json_encode($item));
        $this->redis->lrem('validation_pending', 0, $id);
        
        return $item;
    }
}