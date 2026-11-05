<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LogViewerController extends Controller
{
    public function index()
    {
        $logFile = storage_path('logs/laravel.log');
        
        if (!File::exists($logFile)) {
            return response()->json([
                'success' => false,
                'message' => 'Log file not found'
            ]);
        }
        
        // Get last 500 lines
        $lines = $this->tail($logFile, 500);
        
        // Filter for document processing logs
        $processingLogs = array_filter($lines, function($line) {
            return strpos($line, '[RULE-BASED]') !== false ||
                   strpos($line, '[HUGGING FACE]') !== false ||
                   strpos($line, '[HYBRID]') !== false;
        });
        
        return response()->json([
            'success' => true,
            'logs' => array_values($processingLogs),
            'total_lines' => count($processingLogs)
        ]);
    }
    
    public function clear()
    {
        $logFile = storage_path('logs/laravel.log');
        
        if (File::exists($logFile)) {
            File::put($logFile, '');
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Logs cleared successfully'
        ]);
    }
    
    private function tail($file, $lines = 100)
    {
        $handle = fopen($file, "r");
        $linecounter = $lines;
        $pos = -2;
        $beginning = false;
        $text = [];
        
        while ($linecounter > 0) {
            $t = " ";
            while ($t != "\n") {
                if (fseek($handle, $pos, SEEK_END) == -1) {
                    $beginning = true;
                    break;
                }
                $t = fgetc($handle);
                $pos--;
            }
            $linecounter--;
            if ($beginning) {
                rewind($handle);
            }
            $text[$lines - $linecounter - 1] = fgets($handle);
            if ($beginning) break;
        }
        fclose($handle);
        
        return array_reverse($text);
    }
}
