<?php

declare(strict_types=1);

namespace Ublaboo\Mailing\Tests\Unit;

use Latte\Engine;
use Mockery;
use Nette\Application\LinkGenerator;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Bridges\ApplicationLatte\TemplateFactory;
use Nette\Mail\Mailer;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;
use Ublaboo\Mailing\DI\MailingExtension;
use Ublaboo\Mailing\ILogger;
use Ublaboo\Mailing\MailFactory;

require __DIR__ . '/../bootstrap.php';
Environment::bypassFinals();

final class MailTest extends TestCase
{

	public function createMail(string $config): array
	{
		$mailImagesBasePath = 'www';
		$mails = ['recipient' => 'mail@ma.il'];

		$mailer          = Mockery::mock(Mailer::class);
		$linkGenerator   = Mockery::mock(LinkGenerator::class);
		$templateFactory = Mockery::mock(TemplateFactory::class);
		$logger          = Mockery::mock(ILogger::class);
		$template        = Mockery::mock(Template::class);
		$latte           = Mockery::mock(Engine::class);

		$templateFactory->shouldReceive('createTemplate')->andReturn($template);

		$latte->shouldReceive('addProvider');

		$template->file = false;
		$template->shouldReceive('setFile')->andSet('file', true);
		$template->shouldReceive('getLatte')->andReturn($latte);

		$mailer->sent = false;
		$mailer->shouldReceive('send')->andSet('sent', true);

		$logger->logged = false;
		$logger->shouldReceive('log')->andSet('logged', true);

		$mailFactory = new MailFactory(
			$config,
			$mailImagesBasePath,
			$mails,
			$mailer,
			$linkGenerator,
			$templateFactory,
			$logger
		);

		$mail = $mailFactory->createByType(
			TestingMail::class,
			new TestingMailData('Name', 'from@fro.m')
		);

		return [$mail, $template, $logger, $mailer];
	}


	public function testDoBoth(): void
	{
		[$mail, $template, $logger, $mailer] = $this->createMail(
			MailingExtension::CONFIG_BOTH
		);

		$mail->send();
		$this->destroyTmpTemplateFile('TestingMail');

		Assert::true($template->file, 'Mail template has file set (do = both)');
		Assert::true($logger->logged, 'Mail logged (do = both)');
		Assert::true($mailer->sent, 'Mail sent (do = both)');
	}


	public function testDoSend(): void
	{
		[$mail, $template, $logger, $mailer] = $this->createMail(
			MailingExtension::CONFIG_SEND
		);

		$mail->send();
		$this->destroyTmpTemplateFile('TestingMail');

		Assert::true($template->file, 'Mail template has file set (do = send)');
		Assert::false($logger->logged, 'Mail logged (do = send)');
		Assert::true($mailer->sent, 'Mail sent (do = send)');
	}


	public function testDoLog(): void
	{
		[$mail, $template, $logger, $mailer] = $this->createMail(
			MailingExtension::CONFIG_LOG
		);

		$mail->send();
		$this->destroyTmpTemplateFile('TestingMail');

		Assert::true($template->file, 'Mail template has file set (do = log)');
		Assert::true($logger->logged, 'Mail logged (do = log)');
		Assert::false($mailer->sent, 'Mail sent (do = log)');
	}


	public function testSetBody(): void
	{
		[$mail, $template, $logger, $mailer] = $this->createMail(
			MailingExtension::CONFIG_BOTH
		);

		$mail->send();

		Assert::true($logger->logged, 'Mail logged');
		Assert::true($mailer->sent, 'Mail sent');
	}


	private function getTmpTemplateFile(string $name): string
	{
		$name = lcfirst(preg_replace_callback('/(?<=.)([A-Z])/', function ($m) {
			return '_' . strtolower($m[1]);
		}, $name));

		$path = __DIR__ . "/../templates/$name.latte";

		@mkdir(dirname($path));
		$fh = fopen($path, 'w+');
		fwrite($fh, '<!DOCTYPE html><html><head><title>Contact</title></head><body>{$name}{$email}</body></html>');
		fclose($fh);

		return $path;
	}


	private function destroyTmpTemplateFile(string $name): void
	{
		if (file_exists(__DIR__ . "/../templates/$name.latte")) {
			unlink(__DIR__ . "/../templates/$name.latte");
		}

		if (file_exists(__DIR__ . '/../templates') && is_dir(__DIR__ . '/../templates')) {
			rmdir(__DIR__ . '/../templates');
		}
	}

}

$test_case = new MailTest;
$test_case->run();
