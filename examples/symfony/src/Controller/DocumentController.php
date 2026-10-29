<?php

namespace App\Controller;

use AttachmentAV\AttachmentAVApi;
use AttachmentAV\Exception\ExceptionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Async scan: the upload is kept in the staging directory until attachmentAV has downloaded and scanned it.
 * The scan result arrives at AttachmentAVCallbackController.
 */
class DocumentController extends AbstractController
{
    public function __construct(
        private AttachmentAVApi $attachmentAV,
        private UriSigner $uriSigner,
        private Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%/var/staging')] private string $stagingDir,
        #[Autowire('%kernel.project_dir%/var/results')] private string $resultsDir,
    ) {
    }

    #[Route('/documents', name: 'document_upload', methods: ['GET', 'POST'])]
    public function upload(Request $request): Response
    {
        if (!$this->attachmentAV->getConfiguration()->isSaas()) {
            return $this->render('documents.html.twig', ['error' => 'Async scans are available for the attachmentAV API (SaaS) only.']);
        }

        // The download and callback URLs are generated from the URL of this page and must be reachable from the Internet.
        if (in_array($request->getHost(), ['localhost', '127.0.0.1'], true)) {
            return $this->render('documents.html.twig', ['error' => 'attachmentAV cannot reach localhost. Open this page through a public URL, e.g. with ngrok (see README).']);
        }

        if (!$request->isMethod('POST')) {
            return $this->render('documents.html.twig');
        }

        // PHP discards the whole request body if it exceeds post_max_size, including the CSRF token.
        if ($request->request->count() === 0 && $request->files->count() === 0 && (int) $request->server->get('CONTENT_LENGTH') > 0) {
            return $this->render('documents.html.twig', ['error' => 'The file must not be larger than 100 MB.'], new Response('', 413));
        }

        if (!$this->isCsrfTokenValid('documents', (string) $request->request->get('_token'))) {
            return $this->render('documents.html.twig', ['error' => 'Invalid CSRF token, please try again.'], new Response('', 400));
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            $message = $file instanceof UploadedFile ? $file->getErrorMessage() : 'Please select a file.';

            return $this->render('documents.html.twig', ['error' => $message], new Response('', 422));
        }

        $filename = $file->getClientOriginalName();
        $id = bin2hex(random_bytes(16));
        $file->move($this->stagingDir, $id);
        $this->filesystem->dumpFile($this->resultsDir . '/' . $id . '.json', json_encode(['filename' => $filename, 'status' => 'pending']));

        // attachmentAV downloads the file from this URL. The signature expires after one hour.
        $downloadUrl = $this->uriSigner->sign(
            $this->generateUrl('document_staging', ['id' => $id], UrlGeneratorInterface::ABSOLUTE_URL),
            new \DateInterval('PT1H')
        );

        try {
            $this->attachmentAV->scanAsyncDownload(
                $downloadUrl,
                callbackUrl: $this->generateUrl('attachmentav_callback', [], UrlGeneratorInterface::ABSOLUTE_URL),
                customData: json_encode(['id' => $id, 'filename' => $filename])
            );
        } catch (ExceptionInterface $e) {
            $this->filesystem->remove([$this->stagingDir . '/' . $id, $this->resultsDir . '/' . $id . '.json']);

            return $this->render('documents.html.twig', [
                'filename' => $filename,
                'error' => $e->getMessage(),
            ], new Response('', 502));
        }

        return $this->redirectToRoute('document_show', ['id' => $id]);
    }

    #[Route('/documents/{id}', name: 'document_show', methods: ['GET'], requirements: ['id' => '[0-9a-f]{32}'])]
    public function show(string $id): Response
    {
        $path = $this->resultsDir . '/' . $id . '.json';
        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        return $this->render('document.html.twig', ['document' => json_decode((string) file_get_contents($path), true)]);
    }

    #[Route('/documents/staging/{id}', name: 'document_staging', methods: ['GET'], requirements: ['id' => '[0-9a-f]{32}'])]
    public function staging(Request $request, string $id): Response
    {
        if (!$this->uriSigner->checkRequest($request)) {
            throw $this->createAccessDeniedException();
        }

        $path = $this->stagingDir . '/' . $id;
        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        // attachmentAV detects the real file type itself, no need to guess the MIME type.
        return new BinaryFileResponse($path, headers: ['Content-Type' => 'application/octet-stream']);
    }
}
