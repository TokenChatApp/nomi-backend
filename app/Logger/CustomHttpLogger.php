<?php

namespace App\Logger;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\HttpLogger\LogWriter;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CustomHttpLogger implements LogWriter
{
    public function logRequest(Request $request)
    {
        $method = strtoupper($request->getMethod());

        $uri = $request->getPathInfo();

        $bodyAsJson = json_encode($request->except(config('http-logger.except')));

        $files = array();
        if ($request->photos) {
            
        }
        else {
            $files = array_map(function (UploadedFile $file) {
                return $file->getClientOriginalName();
            }, iterator_to_array($request->files));
        }

        $message = "{$method} {$uri} - Body: {$bodyAsJson} - Files: ".implode(', ', $files);

        Log::info($message);
    }
}