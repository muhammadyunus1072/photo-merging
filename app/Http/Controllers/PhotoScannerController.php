<?php

namespace App\Http\Controllers;

use App\Jobs\OptimizeStudentPhoto;
use App\Models\ScannedPhoto;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PhotoScannerController extends Controller
{
    public function scanFolder(string $folder)
    {
        // The path relative to storage/app/
        $directory = "{$folder}";

        // 1. Validate directory exists
        if (!Storage::disk('private')->exists($directory)) {
            return response()->json([
                'error' => "Directory [{$directory}] does not exist."
            ], 404);
        }

        // 2. Retrieve all files in the directory
        $files = Storage::disk('private')->files($directory);

        $validExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $insertData = [];
        $now = Carbon::now();

        foreach ($files as $file) {
            $extension = Str::lower(pathinfo($file, PATHINFO_EXTENSION));

            // 3. Filter out non-image files (like hidden OS files)
            if (in_array($extension, $validExtensions)) {
                $insertData[] = [
                    'folder' => $folder,
                    'filename' => basename($file),
                    'status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (empty($insertData)) {
            return response()->json(['message' => 'No valid images found to insert.']);
        }

        // 4. Upsert to the database
        // This inserts new records and updates the 'updated_at' column if the record already exists
        ScannedPhoto::upsert(
            $insertData,
            ['folder', 'filename'], // Unique columns to check against
            ['updated_at']          // Columns to update if a match is found
        );
        foreach ($insertData as $data) {
            OptimizeStudentPhoto::dispatch(
                $data['folder'],
                $data['filename']
            );
        }

        return response()->json([
            'message' => 'Scan complete',
            'scanned_count' => count($insertData)
        ]);
    }
}
