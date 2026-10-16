<?php

namespace App\Controller;

use AttachmentAV\AttachmentAVApi;
use AttachmentAV\Exception\ExceptionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ScanController extends AbstractController
{
    // The attachmentAV API (SaaS) accepts files up to 10 MB for sync scans.
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    public function __construct(private AttachmentAVApi $attachmentAV)
    {
    }

    #[Route('/', name: 'scan', methods: ['GET', 'POST'])]
    public function scan(Request $request): Response
    {
        if (!$request->isMethod('POST')) {
            return $this->render('scan.html.twig');
        }

        // PHP discards the whole request body if it exceeds post_max_size, including the CSRF token.
        if ($request->request->count() === 0 && $request->files->count() === 0 && (int) $request->server->get('CONTENT_LENGTH') > 0) {
            return $this->render('scan.html.twig', ['error' => 'The file must not be larger than 10 MB.'], new Response('', 413));
        }

        if (!$this->isCsrfTokenValid('scan', (string) $request->request->get('_token'))) {
            return $this->render('scan.html.twig', ['error' => 'Invalid CSRF token, please try again.'], new Response('', 400));
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            $message = $file instanceof UploadedFile ? $file->getErrorMessage() : 'Please select a file.';

            return $this->render('scan.html.twig', ['error' => $message], new Response('', 422));
        }
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return $this->render('scan.html.twig', ['error' => 'The file must not be larger than 10 MB.'], new Response('', 422));
        }

        try {
            $result = $this->attachmentAV->scanSyncFile($file->getPathname());
        } catch (ExceptionInterface $e) {
            return $this->render('scan.html.twig', [
                'filename' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ], new Response('', 502));
        }

        return $this->render('scan.html.twig', [
            'filename' => $file->getClientOriginalName(),
            'result' => $result,
        ]);
    }
}
