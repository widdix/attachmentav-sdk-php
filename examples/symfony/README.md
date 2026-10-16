# attachmentAV SDK: Symfony example

A minimal Symfony app with a file upload form. The uploaded file is scanned with the attachmentAV SDK, and the page shows the scan result.

## Run

Docker is required. From this directory:

```bash
# attachmentAV API (SaaS)
ATTACHMENTAV_API_KEY=<API_KEY_PLACEHOLDER> ATTACHMENTAV_REGION=eu docker compose up --build

# attachmentAV API (Self-hosted on AWS)
ATTACHMENTAV_DEPLOYMENT=self-hosted ATTACHMENTAV_API_KEY=<API_KEY_PLACEHOLDER> \
  ATTACHMENTAV_BASE_URL=https://attachmentav.yourcompany.com/api/v1 docker compose up --build
```

Open http://localhost:8001 and upload a file. To see a detection, upload the [EICAR test file](https://www.eicar.org/download-anti-malware-testfile/).

The image creates a fresh Symfony project, installs the SDK from this repository, and copies the example files into it. It uses PHP's built-in web server, which is fine for trying the example but not for production.

## Files

* [`config/packages/attachmentav.yaml`](config/packages/attachmentav.yaml): registers `AttachmentAV\AttachmentAVApi` as a service, configured from environment variables.
* [`src/Controller/ScanController.php`](src/Controller/ScanController.php): checks the CSRF token and the upload, then calls `AttachmentAVApi::scanSyncFile()`.
* [`templates/scan.html.twig`](templates/scan.html.twig): the upload form and the scan result.
