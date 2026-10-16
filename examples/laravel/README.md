# attachmentAV SDK: Laravel example

A minimal Laravel app with a file upload form. The uploaded file is scanned with the attachmentAV SDK, and the page shows the scan result.

## Run

Docker is required. From this directory:

```bash
# attachmentAV API (SaaS)
ATTACHMENTAV_API_KEY=<API_KEY_PLACEHOLDER> ATTACHMENTAV_REGION=eu docker compose up --build

# attachmentAV API (Self-hosted on AWS)
ATTACHMENTAV_DEPLOYMENT=self-hosted ATTACHMENTAV_API_KEY=<API_KEY_PLACEHOLDER> \
  ATTACHMENTAV_BASE_URL=https://attachmentav.yourcompany.com/api/v1 docker compose up --build
```

Open http://localhost:8000 and upload a file. To see a detection, upload the [EICAR test file](https://www.eicar.org/download-anti-malware-testfile/).

The image creates a fresh Laravel project, installs the SDK from this repository, and copies the example files into it. It uses PHP's built-in web server, which is fine for trying the example but not for production.

## Files

* [`app/Http/Controllers/ScanController.php`](app/Http/Controllers/ScanController.php): validates the upload and calls `AttachmentAVApi::scanSyncFile()`. The SDK is injected by the auto-discovered service provider.
* [`routes/web.php`](routes/web.php): the routes for the form and the upload.
* [`resources/views/scan.blade.php`](resources/views/scan.blade.php): the upload form and the scan result.

To reject infected uploads during validation instead, use the `VirusFree` rule: `'file' => ['required', 'file', 'max:10240', new VirusFree()]`.
