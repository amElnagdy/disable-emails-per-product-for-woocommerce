<?php

namespace Tests\Regression;

use Tests\Fixtures\DeletedProductOrderFixture;
use Tests\Fixtures\HposToggleFixture;
use Tests\Fixtures\SuppressionConfigurationFixture;
use Tests\Support\RecipientCapture;

/**
 * Abstract base class for the Phase 5 regression suite.
 *
 * Instantiates reusable fixtures and the recipient-capture utility
 * in setUp(); tears them down in reverse order in tearDown().
 */
abstract class AbstractRegressionTest extends \WP_UnitTestCase
{

	/**
	 * @var DeletedProductOrderFixture
	 */
	protected $deleted_product_fixture;

	/**
	 * @var HposToggleFixture
	 */
	protected $hpos_fixture;

	/**
	 * @var SuppressionConfigurationFixture
	 */
	protected $suppression_fixture;

	/**
	 * @var RecipientCapture
	 */
	protected $capture;

	/**
	 * @var array<int, array{type: int, message: string, file: string, line: int}>
	 */
	private array $php_diagnostics = [];

	/**
	 * @var callable|null
	 */
	private $previous_error_handler;

	protected function setUp(): void
	{
		parent::setUp();

		$this->deleted_product_fixture = new DeletedProductOrderFixture();
		$this->hpos_fixture            = new HposToggleFixture();
		$this->suppression_fixture     = new SuppressionConfigurationFixture();
		$this->capture                 = new RecipientCapture();
		$this->capture->install();

		$this->php_diagnostics = [];
		$this->previous_error_handler = set_error_handler(
			function (int $errno, string $errstr, string $errfile, int $errline): bool {
				$this->php_diagnostics[] = [
					'type'    => $errno,
					'message' => $errstr,
					'file'    => $errfile,
					'line'    => $errline,
				];
				return false; // Let PHP's default handler also fire.
			}
		);
	}

	protected function tearDown(): void
	{
		if ($this->previous_error_handler !== null) {
			restore_error_handler();
			$this->previous_error_handler = null;
		}

		if ($this->capture !== null) {
			$this->capture->uninstall();
		}

		if ($this->suppression_fixture !== null) {
			$this->suppression_fixture->tear_down();
		}

		if ($this->hpos_fixture !== null) {
			$this->hpos_fixture->tear_down();
		}

		if ($this->deleted_product_fixture !== null) {
			$this->deleted_product_fixture->tear_down();
		}

		parent::tearDown();
	}

	/**
	 * Assert that no PHP error, warning, or notice was raised during the test.
	 *
	 * @param string $message Optional failure message.
	 */
	protected function assertNoPhpDiagnostic(string $message = ''): void
	{
		$diagnostics = [];
		foreach ($this->php_diagnostics as $diag) {
			$diagnostics[] = sprintf(
				'[%s] %s in %s:%d',
				$this->errno_to_string($diag['type']),
				$diag['message'],
				$diag['file'],
				$diag['line']
			);
		}

		if ($message === '') {
			$message = 'PHP diagnostics were raised during test execution:';
		}

		$this->assertEmpty(
			$diagnostics,
			$message . "\n" . implode("\n", $diagnostics)
		);
	}

	/**
	 * Map an errno integer to a human-readable string.
	 *
	 * @param int $errno PHP error level.
	 * @return string Human-readable label.
	 */
	private function errno_to_string(int $errno): string
	{
		$map = [
			E_ERROR             => 'E_ERROR',
			E_WARNING           => 'E_WARNING',
			E_PARSE             => 'E_PARSE',
			E_NOTICE            => 'E_NOTICE',
			E_CORE_ERROR        => 'E_CORE_ERROR',
			E_CORE_WARNING      => 'E_CORE_WARNING',
			E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
			E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
			E_USER_ERROR        => 'E_USER_ERROR',
			E_USER_WARNING      => 'E_USER_WARNING',
			E_USER_NOTICE       => 'E_USER_NOTICE',
			E_STRICT            => 'E_STRICT',
			E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
			E_DEPRECATED        => 'E_DEPRECATED',
			E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
		];
		return $map[$errno] ?? 'UNKNOWN';
	}
}
