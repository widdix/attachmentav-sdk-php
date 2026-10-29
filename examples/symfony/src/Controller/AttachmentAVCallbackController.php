<?php

namespace App\Controller;

use AttachmentAV\Callback\CallbackVerifier;
use AttachmentAV\Callback\InvalidSignatureException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Receives the scan results of async scans submitted by DocumentController.
 */
class AttachmentAVCallbackController extends AbstractController
{
    public function __construct(
        private CallbackVerifier $verifier,
        private Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%/var/staging')] private string $stagingDir,
        #[Autowire('%kernel.project_dir%/var/documents')] private string $documentsDir,
        #[Autowire('%kernel.project_dir%/var/results')] private string $resultsDir,
    ) {
    }

    #[Route('/attachmentav/callback', name: 'attachmentav_callback', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        try {
            $payload = $this->verifier->verifyAndParse(
                $request->getContent(),
                $request->headers->get('X-Signature'),
                $request->headers->get('X-Timestamp'),
                $this->generateUrl('attachmentav_callback', [], UrlGeneratorInterface::ABSOLUTE_URL)
            );
        } catch (InvalidSignatureException) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }

        // The signature covers the custom data, so it is exactly what DocumentController sent.
        $data = json_decode((string) $payload->getCustomData(), true);
        $staged = $this->stagingDir . '/' . $data['id'];

        // attachmentAV may deliver a callback more than once.
        if (!is_file($staged)) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        if ($payload->isClean()) {
            $this->filesystem->mkdir($this->documentsDir);
            $this->filesystem->rename($staged, $this->documentsDir . '/' . $data['id']);
        } else {
            // 'infected' or 'no' (could not be scanned)
            $this->filesystem->remove($staged);
        }

        $this->filesystem->dumpFile($this->resultsDir . '/' . $data['id'] . '.json', json_encode([
            'filename' => $data['filename'],
            'status' => $payload->getStatus(),
            'finding' => $payload->getFinding(),
            'size' => $payload->getSize(),
            'realFileType' => $payload->getRealFileType(),
        ]));

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
