<?php

declare(strict_types=1);

/**
 * @copyright   Copyright (c) 2015 ublaboo <ublaboo@paveljanda.com>
 * @author      Pavel Janda <me@paveljanda.com>
 * @package     Ublaboo
 */

namespace Ublaboo\Mailing;

use Nette\Application\LinkGenerator;
use Nette\Bridges\ApplicationLatte\TemplateFactory;
use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Ublaboo\Mailing\Exception\MailingMailCreationException;

class MailFactory
{
	private string $config;
	private Message $message;
	private array $mails;
	private string $mailImagesBasePath;


	public function __construct(
		string $config,
		string $mailImagesBasePath,
		array $mails,

		private readonly Mailer $mailer,
		private readonly LinkGenerator $linkGenerator,
		private readonly TemplateFactory $templateFactory,
		private readonly ILogger $logger
	) {
		$this->config = $config;
		$this->mailImagesBasePath = $mailImagesBasePath;
		$this->mails = $mails;
	}


	/**
	 * @template T of IComposableMail
	 * @param class-string<T> $type
	 * @return IComposableMail<T>
	 * @throws MailingMailCreationException
	 */
	public function createByType(string $type, ?IMessageData $mailData): IComposableMail
	{
		$this->message = new Message;

		if (class_exists($type)) {
			$mail = new $type(
				$this->config,
				$this->mails,
				$this->mailer,
				$this->message,
				$this->linkGenerator,
				$this->templateFactory,
				$this->logger,
				$mailData
			);

			$mail->setBasePath($this->mailImagesBasePath);

			if (!$mail instanceof IComposableMail) {
				throw new MailingMailCreationException(
					sprintf(
						'Email of type %s does not implement %s',
						$type,
						IComposableMail::class
					)
				);
			}

			return $mail;
		}

		throw new MailingMailCreationException(sprintf('Email of type %s does not exist', $type));
	}
}
