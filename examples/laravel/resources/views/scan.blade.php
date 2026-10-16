<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>attachmentAV – Laravel example</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 40rem; margin: 3rem auto; padding: 0 1rem; color: #1f2937; }
        form { display: flex; gap: .5rem; align-items: center; margin: 1.5rem 0; }
        button { padding: .4rem 1rem; cursor: pointer; }
        .result { padding: 1rem; border-radius: .5rem; border: 1px solid; }
        .clean { background: #ecfdf5; border-color: #10b981; }
        .infected { background: #fef2f2; border-color: #ef4444; }
        .no, .error { background: #fffbeb; border-color: #f59e0b; }
        dt { font-weight: 600; } dd { margin: 0 0 .5rem; }
    </style>
</head>
<body>
    <h1>Virus and malware scan</h1>
    <p>Upload a file (max. 10 MB) to scan it with attachmentAV.</p>

    <form method="post" action="{{ route('scan.scan') }}" enctype="multipart/form-data">
        @csrf
        <input type="file" name="file" required>
        <button type="submit">Scan</button>
    </form>

    @error('file')
        <div class="result error">{{ $message }}</div>
    @enderror

    @isset($error)
        <div class="result error"><strong>{{ $filename }}</strong> could not be scanned: {{ $error }}</div>
    @endisset

    @isset($result)
        <div class="result {{ $result->getStatus() }}">
            <dl>
                <dt>File</dt><dd>{{ $filename }}</dd>
                <dt>Status</dt><dd>{{ $result->getStatus() }}</dd>
                @if ($result->getFinding())
                    <dt>Finding</dt><dd>{{ $result->getFinding() }}</dd>
                @endif
                <dt>Size</dt><dd>{{ $result->getSize() }} bytes</dd>
                <dt>Real file type</dt><dd>{{ $result->getRealFileType() ?? 'unknown' }}</dd>
            </dl>
        </div>
    @endisset
</body>
</html>
