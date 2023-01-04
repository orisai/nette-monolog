<?php declare(strict_types = 1);

namespace Tests\OriNette\Monolog\Doubles;

use Nette\Mail\Mailer;
use Nette\Mail\Message;

final class TestMailer implements Mailer
{

	public array $mails = [];

	public function send(Message $mail): void
	{
		$this->mails[] = $mail;
	}

}
