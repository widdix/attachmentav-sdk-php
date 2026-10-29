# attachmentAV SDK: Symfony example

A minimal Symfony app with two file upload forms:

* **Sync scan** (`/`): the uploaded file is scanned with the attachmentAV SDK, and the page shows the scan result.
* **Async scan** (`/documents`, SaaS only): the uploaded file is kept in a staging directory. attachmentAV downloads the file through a signed URL, scans it in the background, and sends the scan result to a callback URL.

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

## Run the async scan

attachmentAV must reach the app from the Internet to download the file and to send the callback. Use a tunnel such as [ngrok](https://ngrok.com/).

First, get your tenant ID. It is required to verify the signatures of callbacks.

```bash
ATTACHMENTAV_API_KEY=<API_KEY_PLACEHOLDER> ATTACHMENTAV_REGION=eu docker compose run --rm --build app php -r \
  'require "vendor/autoload.php"; echo (new AttachmentAV\AttachmentAVApi(AttachmentAV\Configuration::saas(getenv("ATTACHMENTAV_API_KEY"), getenv("ATTACHMENTAV_REGION"))))->whoami()->getTenantId(), PHP_EOL;'
```

Second, start the app with the tenant ID, and open a tunnel in a second terminal.

```bash
ATTACHMENTAV_API_KEY=<API_KEY_PLACEHOLDER> ATTACHMENTAV_REGION=eu ATTACHMENTAV_TENANT_ID=<TENANT_ID_PLACEHOLDER> docker compose up --build

ngrok http 8001
```

Third, open `https://<your-subdomain>.ngrok-free.app/documents`, not `localhost`: the download and callback URLs are generated from the URL of the page. Upload a file (max. 100 MB). The result page refreshes until the callback has arrived. Clean files are moved to `var/documents`, all other files are deleted.

The example trusts the `X-Forwarded-*` headers of whoever connects, so that Symfony generates the public HTTPS URLs behind the tunnel. In production, set `TRUSTED_PROXIES` to the IP addresses of your reverse proxies.

## Files

* [`config/packages/attachmentav.yaml`](config/packages/attachmentav.yaml): registers `AttachmentAV\AttachmentAVApi` and `AttachmentAV\Callback\CallbackVerifier` as services, configured from environment variables.
* [`config/packages/trusted_proxies.yaml`](config/packages/trusted_proxies.yaml): trusts the `X-Forwarded-*` headers of the tunnel or reverse proxy.
* [`src/Controller/ScanController.php`](src/Controller/ScanController.php): sync scan. Checks the CSRF token and the upload, then calls `AttachmentAVApi::scanSyncFile()`.
* [`src/Controller/DocumentController.php`](src/Controller/DocumentController.php): async scan. Moves the upload to the staging directory, signs a download URL with Symfony's `UriSigner`, calls `AttachmentAVApi::scanAsyncDownload()`, and serves the staged file to attachmentAV.
* [`src/Controller/AttachmentAVCallbackController.php`](src/Controller/AttachmentAVCallbackController.php): verifies the callback with `CallbackVerifier`, then moves the clean file out of the staging directory or deletes it.
* [`templates/`](templates): the upload forms and the scan results.
