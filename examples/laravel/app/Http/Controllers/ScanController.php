<?php

namespace App\Http\Controllers;

use AttachmentAV\AttachmentAVApi;
use AttachmentAV\Exception\ExceptionInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScanController extends Controller
{
    public function show(): View
    {
        return view('scan');
    }

    public function scan(Request $request, AttachmentAVApi $attachmentAV): View
    {
        // The attachmentAV API (SaaS) accepts files up to 10 MB for sync scans.
        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $file = $request->file('file');

        try {
            $result = $attachmentAV->scanSyncFile($file->getRealPath());
        } catch (ExceptionInterface $e) {
            return view('scan', [
                'filename' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);
        }

        return view('scan', [
            'filename' => $file->getClientOriginalName(),
            'result' => $result,
        ]);
    }
}
