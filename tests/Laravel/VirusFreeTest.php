<?php

declare(strict_types=1);

namespace AttachmentAV\Tests\Laravel;

use AttachmentAV\Exception\InvalidArgumentException;
use AttachmentAV\Laravel\Rules\VirusFree;
use GuzzleHttp\Psr7\Request;
use Http\Client\Exception\NetworkException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

final class VirusFreeTest extends LaravelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!interface_exists(ValidationRule::class)) {
            self::markTestSkipped('The VirusFree rule requires Laravel 10 or newer.');
        }
    }

    private function validate(VirusFree $rule, mixed $file = null): ?string
    {
        $file = $file ?? UploadedFile::fake()->createWithContent('document.txt', 'hello');
        $validator = Validator::make(['attachment' => $file], ['attachment' => [$rule]]);

        return $validator->fails() ? $validator->errors()->first('attachment') : null;
    }

    public function testCleanFilePasses(): void
    {
        $http = $this->mockApi(self::jsonResponse(200, ['status' => 'clean']));

        self::assertNull($this->validate(new VirusFree()));
        self::assertSame('hello', (string) $http->getLastRequest()->getBody());
    }

    public function testInfectedFileFails(): void
    {
        $this->mockApi(self::jsonResponse(200, ['status' => 'infected', 'finding' => 'EICAR-AV-Test']));

        self::assertSame('The attachment contains a virus or malware.', $this->validate(new VirusFree()));
    }

    public function testUnscannableFile(): void
    {
        $this->mockApi(self::jsonResponse(200, ['status' => 'no']), self::jsonResponse(200, ['status' => 'no']));

        self::assertSame('The attachment could not be scanned for viruses.', $this->validate(new VirusFree()));
        self::assertNull($this->validate(new VirusFree(allowUnscannable: true)));
    }

    public function testNonFileFails(): void
    {
        $this->mockApi();

        self::assertSame('The attachment must be a file.', $this->validate(new VirusFree(), 'not a file'));
    }

    public function testApiErrorFailsByDefault(): void
    {
        $this->mockApi(self::jsonResponse(500, ['error' => 'boom']));

        self::assertSame('The attachment could not be scanned for viruses.', $this->validate(new VirusFree()));
    }

    public function testTransientErrorsPassWhenFailOnErrorIsDisabled(): void
    {
        $http = $this->mockApi(
            self::jsonResponse(503, ['error' => 'unavailable']),
            self::jsonResponse(429, ['error' => 'Rate limit exceeded.'])
        );

        self::assertNull($this->validate(new VirusFree(failOnError: false)));
        self::assertNull($this->validate(new VirusFree(failOnError: false)));

        $http->addException(new NetworkException('timeout', new Request('POST', '/')));
        self::assertNull($this->validate(new VirusFree(failOnError: false)));
    }

    public function testPermanentErrorsFailEvenWhenFailOnErrorIsDisabled(): void
    {
        $this->mockApi(
            self::jsonResponse(401, ['error' => 'Invalid API key.']),
            self::jsonResponse(429, ['error' => 'Quota exceeded.'])
        );

        self::assertSame('The attachment could not be scanned for viruses.', $this->validate(new VirusFree(failOnError: false)));
        self::assertSame('The attachment could not be scanned for viruses.', $this->validate(new VirusFree(failOnError: false)));
    }

    public function testMisconfigurationIsNotSwallowed(): void
    {
        config(['attachmentav.api_key' => '']);

        $this->expectException(InvalidArgumentException::class);
        $this->validate(new VirusFree(failOnError: false));
    }
}
