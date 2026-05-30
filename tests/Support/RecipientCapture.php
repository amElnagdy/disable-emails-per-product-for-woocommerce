<?php

namespace Tests\Support;

/**
 * Capture email recipient strings at the wp_mail and
 * woocommerce_email_recipient_* boundaries.
 *
 * @internal
 */
final class RecipientCapture
{

	/**
	 * @var array<int, array{email_id: string, recipient: string, order_id: int|null}>
	 */
	private array $recorded = [];

	/**
	 * @var array<int, array{hook: string, callback: callable, priority: int}>
	 */
	private array $registered = [];

	/**
	 * Install capture hooks at PHP_INT_MAX priority.
	 */
	public function install(): void
	{
		if (!function_exists('WC') || !WC() || !WC()->mailer()) {
			return;
		}

		$mailer = WC()->mailer()->get_emails();
		if (!is_array($mailer)) {
			return;
		}

		foreach ($mailer as $email) {
			if (!($email instanceof \WC_Email)) {
				continue;
			}
			$hook = 'woocommerce_email_recipient_' . $email->id;
			$callback = function ($recipient, $order) use ($email) {
				$this->record($email->id, (string) $recipient, $order);
				return $recipient;
			};
			add_filter($hook, $callback, PHP_INT_MAX, 2);
			$this->registered[] = ['hook' => $hook, 'callback' => $callback, 'priority' => PHP_INT_MAX];
		}

		$mail_callback = function ($args) {
			if (isset($args['to'])) {
				$this->record('wp_mail', is_array($args['to']) ? implode(', ', $args['to']) : (string) $args['to'], null);
			}
			return $args;
		};
		add_filter('wp_mail', $mail_callback, PHP_INT_MAX);
		$this->registered[] = ['hook' => 'wp_mail', 'callback' => $mail_callback, 'priority' => PHP_INT_MAX];
	}

	/**
	 * Remove every hook registered by install().
	 */
	public function uninstall(): void
	{
		foreach ($this->registered as $reg) {
			remove_filter($reg['hook'], $reg['callback'], $reg['priority']);
		}
		$this->registered = [];
	}

	/**
	 * Clear the recorded recipients array.
	 */
	public function reset(): void
	{
		$this->recorded = [];
	}

	/**
	 * Return the full list of captured recipient records.
	 *
	 * @return array<int, array{email_id: string, recipient: string, order_id: int|null}>
	 */
	public function recorded_recipients(): array
	{
		return $this->recorded;
	}

	/**
	 * Return the most recent recipient for the given email ID.
	 *
	 * @param string $email_id WooCommerce email ID.
	 * @return string Recipient string.
	 * @throws \RuntimeException If no matching record exists.
	 */
	public function latest_recipient_for(string $email_id): string
	{
		// Search in reverse for the latest match.
		for ($i = count($this->recorded) - 1; $i >= 0; $i--) {
			if ($this->recorded[$i]['email_id'] === $email_id) {
				return $this->recorded[$i]['recipient'];
			}
		}
		throw new \RuntimeException("No recipient record found for email ID: {$email_id}");
	}

	/**
	 * Record a recipient observation.
	 *
	 * @param string          $email_id  Email identifier.
	 * @param string          $recipient Recipient string.
	 * @param mixed           $order     Order object or null.
	 */
	private function record(string $email_id, string $recipient, $order = null): void
	{
		$order_id = null;
		if (is_a($order, 'WC_Order')) {
			$order_id = (int) $order->get_id();
		}
		$this->recorded[] = [
			'email_id'   => $email_id,
			'recipient'  => $recipient,
			'order_id'   => $order_id,
		];
	}
}
