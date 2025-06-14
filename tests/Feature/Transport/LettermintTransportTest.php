<?php

use Illuminate\Mail\MailManager;
use Lettermint\Laravel\Transport\LettermintTransportFactory;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    config()->set('services.lettermint.token', 'test-token');

    $this->lettermint = Mockery::mock(\Lettermint\Lettermint::class);
    $this->emailBuilder = Mockery::mock();
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
        ->with('test.txt', $content)
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
