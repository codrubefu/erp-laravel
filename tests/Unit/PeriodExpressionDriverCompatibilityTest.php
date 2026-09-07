<?php

namespace Tests\Unit;

use App\Dashboard\Services\DashboardService;
use App\Reporting\Services\FinancialReportService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

class PeriodExpressionDriverCompatibilityTest extends TestCase
{
    #[DataProvider('driverProvider')]
    public function test_dashboard_period_expression_has_no_bare_date_function_on_sqlsrv(string $driver, bool $shouldContainDateFunction): void
    {
        $this->withDefaultDriver($driver, function () use ($shouldContainDateFunction): void {
            $service = app(DashboardService::class);
            $expression = $this->invokePeriodExpression($service, 'day', 'payments.paid_at');

            $this->assertSame($shouldContainDateFunction, str_starts_with($expression, 'date('), "Unexpected day expression for driver: {$expression}");
        });
    }

    #[DataProvider('driverProvider')]
    public function test_financial_report_period_expression_has_no_bare_date_function_on_sqlsrv(string $driver, bool $shouldContainDateFunction): void
    {
        $this->withDefaultDriver($driver, function () use ($shouldContainDateFunction): void {
            $service = app(FinancialReportService::class);
            $expression = $this->invokePrivate($service, 'periodExpression', ['day']);

            $this->assertSame($shouldContainDateFunction, str_starts_with($expression, 'date('), "Unexpected day expression for driver: {$expression}");
        });
    }

    public static function driverProvider(): array
    {
        return [
            'sqlite (default() date() function is fine)' => ['sqlite', true],
            'mysql (default() date() function is fine)' => ['mysql', true],
            'sqlsrv has no DATE() function, must use CONVERT' => ['sqlsrv', false],
        ];
    }

    private function invokePeriodExpression(DashboardService $service, string $period, string $column): string
    {
        return $this->invokePrivate($service, 'periodExpression', [$period, $column]);
    }

    private function invokePrivate(object $object, string $method, array $args): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }

    private function withDefaultDriver(string $driver, callable $callback): void
    {
        $originalDefault = config('database.default');

        config(['database.connections.period_expression_probe' => ['driver' => $driver, 'database' => ':memory:', 'prefix' => '']]);
        config(['database.default' => 'period_expression_probe']);

        try {
            $callback();
        } finally {
            config(['database.default' => $originalDefault]);
            DB::purge('period_expression_probe');
        }
    }
}
