<?php

declare(strict_types=1);

/**
 * @copyright   Copyright (c) 2015 ublaboo <ublaboo@paveljanda.com>
 * @author      Pavel Janda <me@paveljanda.com>
 * @package     Ublaboo
 */

namespace Ublaboo\Mailing;

use Nette\Application\LinkGenerator;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Bridges\ApplicationLatte\TemplateFactory;
use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Nette\Utils\Arrays;
use ReflectionException;
use Ublaboo\Mailing\DI\MailingExtension;

abstract class AbstractMail
{
	protected array $mailAddresses;

	protected Template $template;
	protected string $mailImagesBasePath;
	private string $config;

	/**
	 * @var array<callable(self): void>
	 */
	private array $onBeforeSend = [];


	public function __construct(
		string $config,
		array $mailAddresses,
		protected Mailer $mailer,
		protected Message $message,
		protected LinkGenerator $linkGenerator,
		TemplateFactory $templateFactory,
		protected ILogger $logger,
		private readonly ?IMessageData $mailData,
	) {
		$this->config = $config;
		$this->mailAddresses = $mailAddresses;
		$this->template = $templateFactory->createTemplate();

		/**
		 * Initiate mail composing
		 */
		if ($this instanceof IComposableMail) {
			$this->compose($this->message, $this->mailData);
		}
	}


	public function setBasePath(string $mailImagesBasePath): void
	{
		$this->mailImagesBasePath = $mailImagesBasePath;
	}


	/**
	 * Render latte template to string and send (and/or log) mail
	 */
	public function send(): void
	{
		$templateName = $this->prepareTemplate();

		$this->message->setHtmlBody((string) $this->template, $this->mailImagesBasePath);

		Arrays::invoke($this->onBeforeSend, $this);

		/**
		 * In case mail sending in on, send message
		 */
		if ($this->config === MailingExtension::CONFIG_BOTH || $this->config === MailingExtension::CONFIG_SEND) {
			$this->mailer->send($this->message);
		}

		/**
		 * In case mail logging is turned on, log message
		 */
		if ($this->config === MailingExtension::CONFIG_LOG || $this->config === MailingExtension::CONFIG_BOTH) {
			$this->logger->log($templateName, $this->message);
		}
	}


	/**
	 * @return string
	 * @throws ReflectionException
	 */
	protected function prepareTemplate(): string
	{
		/**
		 * Template variables..
		 */
		$this->template->mailData = $this->mailData;

		/**
		 * Stick to convention that Email:
		 * 		/FooMail.php
		 *
		 * will have template with path of:
		 * 		/templates/FooMail.latte
		 */
		$mailClassReflection = new \ReflectionClass($this);
		$templateName = $mailClassReflection->getShortName();

		$this->template->setFile(sprintf(
			'%s/templates/%s.latte',
			dirname($mailClassReflection->getFilename()),
			$templateName
		));

		/**
		 * Set body/html body
		 */
		$this->template->getLatte()->addProvider('uiControl', $this->linkGenerator);

		return $templateName;
	}


	public function preview(): string
	{
		$this->prepareTemplate();
		return (string) $this->template;
	}


	public function getMessage(): Message
	{
		return $this->message;
	}
}
