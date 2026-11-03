<?php

/**
 * Verifies typed configuration defaults, strict conversion, immutable snapshots and
 * application-owned settings.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Cases;

use InvalidArgumentException;
use LogicException;
use Phex\Config\ArrayEnvironment;
use Phex\Config\Environment;
use Phex\Config\InvalidConfiguration;
use Phex\Http\Message\Request;
use Phex\Kernel\Application;
use Phex\Kernel\Scope\RequestScope;
use Phex\Tests\Compatibility\Fixtures\Config\MutableEnvironment;
use Phex\Tests\Compatibility\Fixtures\SettingLabel;
use Phex\Tests\Generated\ConfigurationContainer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EnvironmentTest extends TestCase
{
    public function testStringsKeepEmptyZeroWhitespaceAndExplicitDefaultsDistinct(): void
    {
        $environment = new Environment(new ArrayEnvironment(['EMPTY' => '', 'ZERO' => '0', 'SPACE' => '  value  ']));
        $this->assertSame('', $environment->string('EMPTY', 'fallback'));
        $this->assertSame('0', $environment->string('ZERO', 'fallback'));
        $this->assertSame('  value  ', $environment->string('SPACE'));
        $this->assertSame('fallback', $environment->string('MISSING', 'fallback'));
        $this->assertSame('', $environment->string('MISSING', ''));
        $this->assertSame(0, $environment->integer('MISSING', 0));
        $this->assertFalse($environment->boolean('MISSING', false));
    }

    public function testArrayReadersAreImmutableSnapshots(): void
    {
        $values = ['APP_NAME' => 'first', 'EMPTY' => ''];
        $reader = new ArrayEnvironment($values);
        $values['APP_NAME'] = 'changed';
        $this->assertSame('first', $reader->get('APP_NAME'));
        $this->assertSame('', $reader->get('EMPTY'));
        $this->assertNull($reader->get('MISSING'));
    }

    /** @return list<array{string}> */
    public static function requiredReaders(): array
    {
        return [['string'], ['integer'], ['boolean']];
    }

    #[DataProvider('requiredReaders')]
    public function testMissingRequiredValuesAreConfigurationErrors(string $reader): void
    {
        $environment = new Environment(new ArrayEnvironment());
        try {
            if ($reader === 'string') {
                $environment->string('REQUIRED');
            } elseif ($reader === 'integer') {
                $environment->integer('REQUIRED');
            } else {
                $environment->boolean('REQUIRED');
            }
        } catch (InvalidConfiguration $error) {
            $this->assertSame('REQUIRED', $error->variable);
            $this->assertSame('Invalid configuration for REQUIRED: A value is required.', $error->getMessage());

            return;
        }
        $this->fail('Missing required configuration must fail.');
    }

    public function testNonemptyStringsRejectPresentEmptyValuesInsteadOfDefaulting(): void
    {
        $this->expectException(InvalidConfiguration::class);
        (new Environment(new ArrayEnvironment(['VALUE' => ''])))->nonEmptyString('VALUE', 'fallback');
    }

    public function testNonemptyStringDefaultsAreAlsoValidated(): void
    {
        $this->expectException(InvalidConfiguration::class);
        (new Environment(new ArrayEnvironment()))->nonEmptyString('VALUE', '');
    }

    /** @return list<array{string, int}> */
    public static function integers(): array
    {
        return [
            ['0', 0], ['-0', 0], ['000', 0], ['42', 42], ['0042', 42], ['-0042', -42],
            [(string) PHP_INT_MAX, PHP_INT_MAX], [(string) PHP_INT_MIN, PHP_INT_MIN],
        ];
    }

    #[DataProvider('integers')]
    public function testIntegerParsingUsesCheckedSignedDecimalConversion(string $raw, int $expected): void
    {
        $environment = new Environment(new ArrayEnvironment(['VALUE' => $raw]));
        $this->assertSame($expected, $environment->integer('VALUE'));
    }

    /** @return list<array{string}> */
    public static function invalidIntegers(): array
    {
        return [[''], ['+42'], [' 42'], ['42 '], ['1.5'], ['1e2'], ['0x10'], ['true'], ['-'],
            [(string) PHP_INT_MAX.'0'], [(string) PHP_INT_MIN.'0']];
    }

    #[DataProvider('invalidIntegers')]
    public function testInvalidIntegerValuesNeverUseTheirDefault(string $raw): void
    {
        $this->expectException(InvalidConfiguration::class);
        (new Environment(new ArrayEnvironment(['VALUE' => $raw])))->integer('VALUE', 5);
    }

    public function testIntegerBoundsIncludeTheirEndpointsAndValidateDefaults(): void
    {
        $environment = new Environment(new ArrayEnvironment(['MIN' => '1', 'MAX' => '100', 'ZERO' => '0']));
        $this->assertSame(1, $environment->integer('MIN', min: 1, max: 100));
        $this->assertSame(100, $environment->integer('MAX', min: 1, max: 100));
        $this->assertSame(0, $environment->integer('ZERO', min: 0, max: 0));
        $this->assertSame(7, $environment->integer('MISSING', 7, min: 1, max: 100));
    }

    public function testValuesBelowMinimumAreRejected(): void
    {
        $this->expectException(InvalidConfiguration::class);
        (new Environment(new ArrayEnvironment(['VALUE' => '0'])))->integer('VALUE', min: 1);
    }

    public function testValuesAboveMaximumAreRejected(): void
    {
        $this->expectException(InvalidConfiguration::class);
        (new Environment(new ArrayEnvironment(['VALUE' => '101'])))->integer('VALUE', max: 100);
    }

    public function testOutOfRangeDefaultsAreRejected(): void
    {
        $this->expectException(InvalidConfiguration::class);
        (new Environment(new ArrayEnvironment()))->integer('VALUE', 0, min: 1);
    }

    public function testInvertedBoundsAreProgrammingErrors(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Environment(new ArrayEnvironment()))->integer('VALUE', 1, min: 10, max: 1);
    }

    /** @return list<array{string, bool}> */
    public static function booleans(): array
    {
        return [['true', true], ['1', true], ['false', false], ['0', false]];
    }

    #[DataProvider('booleans')]
    public function testBooleanConversionUsesAnExplicitVocabulary(string $raw, bool $expected): void
    {
        $this->assertSame($expected, (new Environment(new ArrayEnvironment(['VALUE' => $raw])))->boolean('VALUE', !$expected));
    }

    /** @return list<array{string}> */
    public static function invalidBooleans(): array
    {
        return [[''], ['TRUE'], ['False'], ['yes'], ['no'], ['on'], ['off'], ['2'], [' true']];
    }

    #[DataProvider('invalidBooleans')]
    public function testInvalidBooleanValuesNeverUseTheirDefault(string $raw): void
    {
        $this->expectException(InvalidConfiguration::class);
        (new Environment(new ArrayEnvironment(['VALUE' => $raw])))->boolean('VALUE', true);
    }

    /** @return list<array{string}> */
    public static function invalidNames(): array
    {
        return [[''], ['1NAME'], ['APP.NAME'], ['APP NAME'], ['APP=NAME'], ["APP\0NAME"], ['APP-NAME']];
    }

    #[DataProvider('invalidNames')]
    public function testVariableNamesAreValidatedBeforeReaderAccess(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Environment(new ArrayEnvironment()))->string($name, 'default');
    }

    public function testNamesRemainCaseSensitiveAndAcceptUnderscoresAndDigits(): void
    {
        $environment = new Environment(new ArrayEnvironment(['_APP_1' => 'one', 'app' => 'lower', 'APP' => 'upper']));
        $this->assertSame('one', $environment->string('_APP_1'));
        $this->assertSame('lower', $environment->string('app'));
        $this->assertSame('upper', $environment->string('APP'));
    }

    public function testConversionErrorsDoNotInterpolateTheRawValue(): void
    {
        try {
            (new Environment(new ArrayEnvironment(['PRIVATE_VALUE' => 'do-not-print-this-value'])))->integer('PRIVATE_VALUE');
        } catch (InvalidConfiguration $error) {
            $this->assertSame('PRIVATE_VALUE', $error->variable);
            $this->assertFalse(str_contains($error->getMessage(), 'do-not-print-this-value'));

            return;
        }
        $this->fail('Invalid configuration must fail without formatting its value.');
    }

    public function testGenericConvertersPreserveConcreteScalarAndObjectResults(): void
    {
        $environment = new Environment(new ArrayEnvironment(['APP_NAME' => 'phex']));
        $label = $environment->map('APP_NAME', fn (string $value): SettingLabel => new SettingLabel($value));
        $upper = $environment->map('APP_NAME', fn (string $value): string => strtoupper($value));
        $this->assertSame('phex', $label->value);
        $this->assertSame('PHEX', $upper);
    }

    public function testCustomConverterFailuresKeepTheirOwnTypeAndMessage(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('custom conversion failed');
        (new Environment(new ArrayEnvironment(['VALUE' => 'present'])))->map('VALUE', function (string $value): string {
            throw new RuntimeException('custom conversion failed');
        });
    }

    public function testApplicationSettingsAreValidatedEagerlyAndReusedWithoutEnvironmentReads(): void
    {
        $container = new ConfigurationContainer();
        $scope = new RequestScope(new Request('GET', '/'));
        try {
            $reader = self::mutable($container->reader());
            $this->assertSame(3, $reader->reads);
            $settings = $container->settings();
            $this->assertSame('first', $settings->greeting);
            $this->assertSame(7, $settings->pageSize);
            $this->assertFalse($settings->uppercaseGreeting);
            $reader->values['PHEX_GREETING'] = 'changed';
            $reader->values['PHEX_PAGE_SIZE'] = '9';
            $this->assertSame($settings, $container->request($scope)->settings());
            $application = new Application($container);
            $this->assertSame('{"greeting":"first","size":7,"uppercase":false}', $application->handle(new Request('GET', '/'))->body);
            $this->assertSame('{"greeting":"first","size":7,"uppercase":false}', $application->handle(new Request('GET', '/'))->body);
            $this->assertSame(3, $reader->reads);
        } finally {
            $scope->close();
            $container->close();
        }
    }

    private static function mutable(mixed $value): MutableEnvironment
    {
        if (!$value instanceof MutableEnvironment) {
            throw new LogicException('The fixture needs its mutable environment reader.');
        }

        return $value;
    }
}
