<?php

declare(strict_types=1);

namespace Angou\Angocore\Mail;

use Illuminate\Mail\Mailable;
use Symfony\Component\Mime\Email;

/**
 * Drop-in compat with `Angou\Angomail\Mail\AngomailTemplate` from the legacy
 * angomail-laravel package. Sub-classes implement templateName() + templateData().
 */
abstract class AngomailTemplate extends Mailable
{
    abstract protected function templateName(): string;

    /** @return array<string, mixed> */
    abstract protected function templateData(): array;

    /** Optional override: explicit "from" email per message. */
    protected function fromOverrideEmail(): ?string
    {
        return null;
    }

    /** Optional override: explicit "from" name per message. */
    protected function fromOverrideName(): ?string
    {
        return null;
    }

    /** Optional override: idempotency key for this send. */
    protected function idempotencyKey(): ?string
    {
        return null;
    }

    public function build(): self
    {
        $template = $this->templateName();
        $data = $this->templateData();

        $encodedData = base64_encode(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        );

        $this->subject('[angocore] '.$template);
        $this->html(' ');

        $fromEmail = $this->fromOverrideEmail();
        $fromName = $this->fromOverrideName();
        $idem = $this->idempotencyKey();

        $this->withSymfonyMessage(static function (Email $email) use ($template, $encodedData, $fromEmail, $fromName, $idem): void {
            $headers = $email->getHeaders();
            $headers->addTextHeader('X-Angocore-Mail-Template', $template);
            $headers->addTextHeader('X-Angocore-Mail-Data', $encodedData);
            if ($fromEmail !== null && $fromEmail !== '') {
                $headers->addTextHeader('X-Angocore-Mail-From-Email', $fromEmail);
            }
            if ($fromName !== null && $fromName !== '') {
                $headers->addTextHeader('X-Angocore-Mail-From-Name', $fromName);
            }
            if ($idem !== null && $idem !== '') {
                $headers->addTextHeader('X-Angocore-Idempotency-Key', $idem);
            }
        });

        return $this;
    }
}
