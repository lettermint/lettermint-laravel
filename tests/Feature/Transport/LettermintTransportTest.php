<?php

use Illuminate\Mail\MailManager;
use Lettermint\Endpoints\EmailEndpoint;
use Lettermint\Laravel\Transport\LettermintTransportFactory;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    config()->set('services.lettermint.token', 'test-token');

    $this->lettermint = Mockery::mock(\Lettermint\Lettermint::class);
    $this->emailBuilder = Mockery::mock(EmailEndpoint::class);
    $this->lettermint->email = $this->emailBuilder;
    $this->transport = new LettermintTransportFactory($this->lettermint);
});

it('creates a transport instance', function () {
    $transport = new LettermintTransportFactory($this->lettermint);

    expect($transport)->toBeInstanceOf(LettermintTransportFactory::class);
});

it('registers the lettermint transport', function () {
    $app = app();

    $app['config']->set('lettermint', [
        'token' => 'test_token_12345',
    ]);

    $manager = $app->get(MailManager::class);

    $transport = $manager->createSymfonyTransport(['transport' => 'lettermint']);

    expect((string) $transport)->toBe('lettermint');
});

it('can send email', function () {
    $email = (new Email)
        ->from('from@example.com')
        ->to(new Address('to@example.com', 'Acme'))
        ->cc('cc@example.com')
        ->bcc('bcc@example.com')
        ->replyTo('reply-to@example.com')
        ->subject('Hello world!')
        ->text('This is a Lettermint test mail.')
        ->html('<p>Test HTML body</p>');

    $this->emailBuilder
        ->shouldReceive('headers')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('from')
        ->once()
        ->with('from@example.com')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('to')
        ->once()
        ->with('"Acme" <to@example.com>')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('cc')
        ->once()
        ->with('cc@example.com')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('bcc')
        ->once()
        ->with('bcc@example.com')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('replyTo')
        ->once()
        ->with('reply-to@example.com')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('subject')
        ->once()
        ->with('Hello world!')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('text')
        ->once()
        ->with('This is a Lettermint test mail.')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('html')
        ->once()
        ->with('<p>Test HTML body</p>')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('send')
        ->once()
        ->andReturn(['id' => '123', 'status' => 'pending']);

    $this->transport->send($email);
});

it('can send to multiple recipients', function () {
    $email = (new Email)
        ->from('from@example.com')
        ->to(
            new Address('to@example.com', 'Acme'),
            new Address('sales@example.com', 'Acme Sales')
        )
        ->subject('Hello world!')
        ->text('This is a Lettermint test mail.');

    $this->emailBuilder
        ->shouldReceive('headers')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('from')
        ->once()
        ->with('from@example.com')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('to')
        ->once()
        ->with('"Acme" <to@example.com>', '"Acme Sales" <sales@example.com>')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('subject')
        ->once()
        ->with('Hello world!')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('text')
        ->once()
        ->with('This is a Lettermint test mail.')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('html')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('cc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('bcc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('replyTo')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('send')
        ->once()
        ->andReturn(['id' => '123', 'status' => 'pending']);

    $this->transport->send($email);
});

it('can send email with headers', function () {
    $email = (new Email)
        ->from('from@example.com')
        ->to(new Address('to@example.com', 'Acme'))
        ->subject('Hello world!')
        ->text('This is a Lettermint test mail.');

    $email->getHeaders()->addHeader('X-Custom-Header', 'test-value');

    $this->emailBuilder
        ->shouldReceive('headers')
        ->once()
        ->with(['X-Custom-Header' => 'test-value'])
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('from')
        ->once()
        ->with('from@example.com')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('to')
        ->once()
        ->with('"Acme" <to@example.com>')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('subject')
        ->once()
        ->with('Hello world!')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('text')
        ->once()
        ->with('This is a Lettermint test mail.')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('html')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('cc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('bcc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('replyTo')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('send')
        ->once()
        ->andReturn(['id' => '123', 'status' => 'pending']);

    $this->transport->send($email);
});

it('can send with attachments', function () {
    $email = (new Email)
        ->from('from@example.com')
        ->to(new Address('to@example.com', 'Acme'))
        ->subject('Hello world!')
        ->text('This is a Lettermint test mail.');

    $content = base64_encode('base64');
    $email->attach('base64', 'test.txt', 'text/plain');

    $this->emailBuilder
        ->shouldReceive('headers')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('from')
        ->once()
        ->with('from@example.com')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('to')
        ->once()
        ->with('"Acme" <to@example.com>')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('subject')
        ->once()
        ->with('Hello world!')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('text')
        ->once()
        ->with('This is a Lettermint test mail.')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('html')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('cc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('bcc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('replyTo')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('attach')
        ->once()
        ->with('test.txt', $content, null)
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('send')
        ->once()
        ->andReturn(['id' => '123', 'status' => 'pending']);

    $this->transport->send($email);
});

it('can send with inline attachments', function () {
    $email = (new Email)
        ->from('from@example.com')
        ->to(new Address('to@example.com', 'Acme'))
        ->subject('Hello world!')
        ->html('<img src="cid:logo@example.com">');

    $content = base64_encode('image-data');

    $image = new \Symfony\Component\Mime\Part\DataPart('image-data', 'logo.png', 'image/png');
    $image->asInline();
    $image->setContentId('logo@example.com');
    $email->addPart($image);

    $this->emailBuilder
        ->shouldReceive('headers')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('from')
        ->once()
        ->with('from@example.com')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('to')
        ->once()
        ->with('"Acme" <to@example.com>')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('subject')
        ->once()
        ->with('Hello world!')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('text')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('html')
        ->once()
        ->with('<img src="cid:logo@example.com">')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('cc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('bcc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('replyTo')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('attach')
        ->once()
        ->with('logo.png', $content, 'logo@example.com')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('send')
        ->once()
        ->andReturn(['id' => '123', 'status' => 'pending']);

    $this->transport->send($email);
});

it('throws transport exception on API error', function () {
    $email = (new Email)
        ->from('from@example.com')
        ->to('to@example.com')
        ->subject('Hello world!')
        ->text('This is a Lettermint test mail.');

    $this->emailBuilder
        ->shouldReceive('headers')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('from')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('to')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('subject')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('text')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('html')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('cc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('bcc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('replyTo')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('send')
        ->once()
        ->andThrow(new Exception('Failed to send email'));

    expect(fn () => $this->transport->send($email))
        ->toThrow(TransportException::class, 'Sending email via Lettermint API failed: Failed to send email');
});

it('can send email with route_id', function () {
    $config = ['route_id' => 'test-route-123'];
    $transport = new LettermintTransportFactory($this->lettermint, $config);

    $email = (new Email)
        ->from('from@example.com')
        ->to(new Address('to@example.com', 'Acme'))
        ->subject('Hello world!')
        ->text('This is a Lettermint test mail.')
        ->html('<p>Test HTML body</p>');

    $this->emailBuilder
        ->shouldReceive('headers')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('from')
        ->once()
        ->with('from@example.com')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('to')
        ->once()
        ->with('"Acme" <to@example.com>')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('cc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('bcc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('replyTo')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('subject')
        ->once()
        ->with('Hello world!')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('text')
        ->once()
        ->with('This is a Lettermint test mail.')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('html')
        ->once()
        ->with('<p>Test HTML body</p>')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('route')
        ->once()
        ->with('test-route-123')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('send')
        ->once()
        ->andReturn(['id' => '123', 'status' => 'pending']);

    $transport->send($email);
});

it('does not call route when route_id is not set', function () {
    $config = [];
    $transport = new LettermintTransportFactory($this->lettermint, $config);

    $email = (new Email)
        ->from('from@example.com')
        ->to(new Address('to@example.com', 'Acme'))
        ->subject('Hello world!')
        ->text('This is a Lettermint test mail.');

    $this->emailBuilder
        ->shouldReceive('headers')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('from')
        ->once()
        ->with('from@example.com')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('to')
        ->once()
        ->with('"Acme" <to@example.com>')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('cc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('bcc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('replyTo')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('subject')
        ->once()
        ->with('Hello world!')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('text')
        ->once()
        ->with('This is a Lettermint test mail.')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('html')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldNotReceive('route');

    $this->emailBuilder
        ->shouldReceive('send')
        ->once()
        ->andReturn(['id' => '123', 'status' => 'pending']);

    $transport->send($email);
});

it('uses route_id from mailer config via mail manager', function () {
    $app = app();

    $app['config']->set('lettermint', [
        'token' => 'test_token_12345',
    ]);

    $manager = $app->get(MailManager::class);

    $transport = $manager->createSymfonyTransport([
        'transport' => 'lettermint',
        'route_id' => 'broadcast',
    ]);

    expect((string) $transport)->toBe('lettermint');

    // Test that the transport has the correct config
    $reflection = new ReflectionClass($transport);
    $configProperty = $reflection->getProperty('config');
    $configProperty->setAccessible(true);
    $config = $configProperty->getValue($transport);

    expect($config)->toHaveKey('route_id');
    expect($config['route_id'])->toBe('broadcast');
});

it('uses custom idempotency key when Idempotency-Key header is set', function () {
    $email = (new Email)
        ->from('from@example.com')
        ->to('to@example.com')
        ->subject('Hello world!')
        ->text('This is a test mail.');

    // Add custom idempotency key header
    $email->getHeaders()->addHeader('Idempotency-Key', 'custom-key-123');

    $this->emailBuilder
        ->shouldReceive('headers')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('from')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('to')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('subject')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('text')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('html')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('cc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('bcc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('replyTo')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('idempotencyKey')
        ->once()
        ->with('custom-key-123')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('send')
        ->once()
        ->andReturn(['id' => '123', 'status' => 'pending']);

    $this->transport->send($email);
});

it('does not include Idempotency-Key in headers sent to API', function () {
    $email = (new Email)
        ->from('from@example.com')
        ->to('to@example.com')
        ->subject('Hello world!')
        ->text('This is a test mail.');

    // Add custom header
    $email->getHeaders()->addHeader('Idempotency-Key', 'custom-key-123');
    $email->getHeaders()->addHeader('X-Custom-Header', 'test-value');

    $this->emailBuilder
        ->shouldReceive('headers')
        ->once()
        ->with(['X-Custom-Header' => 'test-value']) // Should not include Idempotency-Key
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('from')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('to')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('subject')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('text')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('html')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('cc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('bcc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('replyTo')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('idempotencyKey')
        ->once()
        ->with('custom-key-123')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('send')
        ->once()
        ->andReturn(['id' => '123', 'status' => 'pending']);

    $this->transport->send($email);
});

it('can disable idempotency via configuration', function () {
    $config = ['idempotency' => false];
    $transport = new LettermintTransportFactory($this->lettermint, $config);

    $email = (new Email)
        ->from('from@example.com')
        ->to('to@example.com')
        ->subject('Hello world\!')
        ->text('This is a test mail.');

    $this->emailBuilder
        ->shouldReceive('headers')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('from')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('to')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('subject')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('text')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('html')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('cc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('bcc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('replyTo')
        ->once()
        ->andReturnSelf();

    // Should NOT call idempotencyKey when disabled
    $this->emailBuilder
        ->shouldNotReceive('idempotencyKey');

    $this->emailBuilder
        ->shouldReceive('send')
        ->once()
        ->andReturn(['id' => '123', 'status' => 'pending']);

    $transport->send($email);
});

it('header idempotency key works with automatic idempotency enabled', function () {
    $config = ['idempotency' => true]; // Automatic idempotency enabled
    $transport = new LettermintTransportFactory($this->lettermint, $config);

    $email = (new Email)
        ->from('from@example.com')
        ->to('to@example.com')
        ->subject('Hello world\!')
        ->text('This is a test mail.');

    // Add header idempotency key
    $email->getHeaders()->addHeader('Idempotency-Key', 'header-key-789');

    $this->emailBuilder
        ->shouldReceive('headers')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('from')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('to')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('subject')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('text')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('html')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('cc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('bcc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('replyTo')
        ->once()
        ->andReturnSelf();

    // Should use header key instead of automatic Message-ID
    $this->emailBuilder
        ->shouldReceive('idempotencyKey')
        ->once()
        ->with('header-key-789')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('send')
        ->once()
        ->andReturn(['id' => '123', 'status' => 'pending']);

    $transport->send($email);
});

it('respects user-provided idempotency header even when config disables idempotency', function () {
    $config = ['idempotency' => false]; // Disable automatic idempotency
    $transport = new LettermintTransportFactory($this->lettermint, $config);

    $email = (new Email)
        ->from('from@example.com')
        ->to('to@example.com')
        ->subject('Hello world\!')
        ->text('This is a test mail.');

    // Add user-provided idempotency key header
    $email->getHeaders()->addHeader('Idempotency-Key', 'user-override-key');

    $this->emailBuilder
        ->shouldReceive('headers')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('from')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('to')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('subject')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('text')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('html')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('cc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('bcc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('replyTo')
        ->once()
        ->andReturnSelf();

    // Should use the user-provided key despite config being false
    $this->emailBuilder
        ->shouldReceive('idempotencyKey')
        ->once()
        ->with('user-override-key')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('send')
        ->once()
        ->andReturn(['id' => '123', 'status' => 'pending']);

    $transport->send($email);
});

it('generates idempotency key with default 24-hour window', function () {
    $config = ['idempotency' => true]; // Enable automatic idempotency with default window
    $transport = new LettermintTransportFactory($this->lettermint, $config);

    $email = (new Email)
        ->from('from@example.com')
        ->to('to@example.com')
        ->subject('Hello world!')
        ->text('This is a test mail.');

    $this->emailBuilder
        ->shouldReceive('headers')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('from')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('to')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('subject')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('text')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('html')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('cc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('bcc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('replyTo')
        ->once()
        ->andReturnSelf();

    // Should call idempotencyKey with generated hash (no timestamp for 24h+ window)
    $this->emailBuilder
        ->shouldReceive('idempotencyKey')
        ->once()
        ->with(Mockery::type('string'))
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('send')
        ->once()
        ->andReturn(['id' => '123', 'status' => 'pending']);

    $transport->send($email);
});

it('generates idempotency key with custom window', function () {
    $config = ['idempotency' => true, 'idempotency_window' => 3600]; // 1 hour window
    $transport = new LettermintTransportFactory($this->lettermint, $config);

    $email = (new Email)
        ->from('from@example.com')
        ->to('to@example.com')
        ->subject('Hello world!')
        ->text('This is a test mail.');

    $this->emailBuilder
        ->shouldReceive('headers')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('from')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('to')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('subject')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('text')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('html')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('cc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('bcc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('replyTo')
        ->once()
        ->andReturnSelf();

    // Should call idempotencyKey with generated hash (includes timestamp for <24h window)
    $this->emailBuilder
        ->shouldReceive('idempotencyKey')
        ->once()
        ->with(Mockery::type('string'))
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('send')
        ->once()
        ->andReturn(['id' => '123', 'status' => 'pending']);

    $transport->send($email);
});

it('custom idempotency header overrides window configuration', function () {
    $config = ['idempotency' => true, 'idempotency_window' => 300]; // 5 minutes
    $transport = new LettermintTransportFactory($this->lettermint, $config);

    $email = (new Email)
        ->from('from@example.com')
        ->to('to@example.com')
        ->subject('Hello world!')
        ->text('This is a test mail.');

    // Add custom idempotency key header
    $email->getHeaders()->addHeader('Idempotency-Key', 'custom-override');

    $this->emailBuilder
        ->shouldReceive('headers')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('from')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('to')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('subject')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('text')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('html')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('cc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('bcc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('replyTo')
        ->once()
        ->andReturnSelf();

    // Should use custom key, not generated one
    $this->emailBuilder
        ->shouldReceive('idempotencyKey')
        ->once()
        ->with('custom-override')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('send')
        ->once()
        ->andReturn(['id' => '123', 'status' => 'pending']);

    $transport->send($email);
});

it('can send email with tag using TagHeader', function () {
    $email = (new Email)
        ->from('from@example.com')
        ->to('to@example.com')
        ->subject('Hello world!')
        ->text('This is a test mail.');

    $email->getHeaders()->add(new TagHeader('welcome-email'));

    $this->emailBuilder
        ->shouldReceive('headers')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('from')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('to')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('subject')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('text')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('html')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('cc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('bcc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('replyTo')
        ->once()
        ->andReturnSelf();

    // Should call tag method
    $this->emailBuilder
        ->shouldReceive('tag')
        ->once()
        ->with('welcome-email')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('send')
        ->once()
        ->andReturn(['id' => '123', 'status' => 'pending']);

    $this->transport->send($email);
});

it('can send email with metadata using MetadataHeader', function () {
    $email = (new Email)
        ->from('from@example.com')
        ->to('to@example.com')
        ->subject('Hello world!')
        ->text('This is a test mail.');

    $email->getHeaders()->add(new MetadataHeader('user_id', '12345'));
    $email->getHeaders()->add(new MetadataHeader('campaign', 'summer-sale'));

    $this->emailBuilder
        ->shouldReceive('headers')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('from')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('to')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('subject')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('text')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('html')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('cc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('bcc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('replyTo')
        ->once()
        ->andReturnSelf();

    // Should call metadata method with all metadata
    $this->emailBuilder
        ->shouldReceive('metadata')
        ->once()
        ->with(['user_id' => '12345', 'campaign' => 'summer-sale'])
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('send')
        ->once()
        ->andReturn(['id' => '123', 'status' => 'pending']);

    $this->transport->send($email);
});

it('can send email with tag using X-LM-Tag header for backward compatibility', function () {
    $email = (new Email)
        ->from('from@example.com')
        ->to('to@example.com')
        ->subject('Hello world!')
        ->text('This is a test mail.');

    // Add tag using custom header (backward compatibility)
    $email->getHeaders()->addHeader('X-LM-Tag', 'tti-test');

    $this->emailBuilder
        ->shouldReceive('headers')
        ->once()
        ->with([]) // X-LM-Tag should be bypassed
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('from')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('to')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('subject')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('text')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('html')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('cc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('bcc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('replyTo')
        ->once()
        ->andReturnSelf();

    // Should call tag method with the custom header value
    $this->emailBuilder
        ->shouldReceive('tag')
        ->once()
        ->with('tti-test')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('send')
        ->once()
        ->andReturn(['id' => '123', 'status' => 'pending']);

    $this->transport->send($email);
});

it('prefers TagHeader over X-LM-Tag when both are present', function () {
    $email = (new Email)
        ->from('from@example.com')
        ->to('to@example.com')
        ->subject('Hello world!')
        ->text('This is a test mail.');

    // Add both headers - TagHeader should take precedence
    $email->getHeaders()->add(new TagHeader('primary-tag'));
    $email->getHeaders()->addHeader('X-LM-Tag', 'fallback-tag');

    $this->emailBuilder
        ->shouldReceive('headers')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('from')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('to')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('subject')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('text')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('html')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('cc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('bcc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('replyTo')
        ->once()
        ->andReturnSelf();

    // Should use the primary TagHeader value, not the fallback
    $this->emailBuilder
        ->shouldReceive('tag')
        ->once()
        ->with('primary-tag')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('send')
        ->once()
        ->andReturn(['id' => '123', 'status' => 'pending']);

    $this->transport->send($email);
});

it('can send email with both tag and metadata', function () {
    $email = (new Email)
        ->from('from@example.com')
        ->to('to@example.com')
        ->subject('Hello world!')
        ->text('This is a test mail.');

    // Add both tag and metadata
    $email->getHeaders()->add(new TagHeader('user-notification'));
    $email->getHeaders()->add(new MetadataHeader('user_id', '67890'));
    $email->getHeaders()->add(new MetadataHeader('notification_type', 'password_reset'));

    $this->emailBuilder
        ->shouldReceive('headers')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('from')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('to')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('subject')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('text')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('html')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('cc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('bcc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('replyTo')
        ->once()
        ->andReturnSelf();

    // Should call both tag and metadata methods
    $this->emailBuilder
        ->shouldReceive('tag')
        ->once()
        ->with('user-notification')
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('metadata')
        ->once()
        ->with(['user_id' => '67890', 'notification_type' => 'password_reset'])
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('send')
        ->once()
        ->andReturn(['id' => '123', 'status' => 'pending']);

    $this->transport->send($email);
});

it('does not call tag or metadata methods when not provided', function () {
    $email = (new Email)
        ->from('from@example.com')
        ->to('to@example.com')
        ->subject('Hello world!')
        ->text('This is a test mail.');

    $this->emailBuilder
        ->shouldReceive('headers')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('from')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('to')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('subject')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('text')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('html')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('cc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('bcc')
        ->once()
        ->andReturnSelf();

    $this->emailBuilder
        ->shouldReceive('replyTo')
        ->once()
        ->andReturnSelf();

    // Should NOT call tag or metadata methods
    $this->emailBuilder
        ->shouldNotReceive('tag');

    $this->emailBuilder
        ->shouldNotReceive('metadata');

    $this->emailBuilder
        ->shouldReceive('send')
        ->once()
        ->andReturn(['id' => '123', 'status' => 'pending']);

    $this->transport->send($email);
});
