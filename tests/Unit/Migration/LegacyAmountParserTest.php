<?php

declare(strict_types=1);

namespace Tests\Unit\Migration;

use App\Domain\Migration\Support\LegacyAmountParser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LegacyAmountParserTest extends TestCase
{
    private LegacyAmountParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new LegacyAmountParser;
    }

    #[DataProvider('validAmounts')]
    public function test_parses_valid_amounts(string $raw, string $expected): void
    {
        self::assertSame($expected, $this->parser->parse($raw));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function validAmounts(): array
    {
        return [
            'plain' => ['1500', '1500.00'],
            'plain decimal' => ['1500.5', '1500.50'],
            'plain 2dp' => ['1500.50', '1500.50'],
            'id format' => ['1.500.000,25', '1500000.25'],
            'en format' => ['1,500,000.25', '1500000.25'],
            'comma decimal' => ['1500,25', '1500.25'],
            'id thousands' => ['1.500.000', '1500000.00'],
            'en thousands' => ['1,500,000', '1500000.00'],
            'rp prefix' => ['Rp 1500', '1500.00'],
            'rp dot' => ['Rp.1.500,00', '1500.00'],
            'trailing dot' => ['150000.', '150000.00'],
            'negative reversal magnitude' => ['-1000000', '1000000.00'],
            'negative decimal magnitude' => ['-2222.22', '2222.22'],
        ];
    }

    public function test_parse_signed_flags_negative(): void
    {
        $pos = $this->parser->parseSigned('1500');
        self::assertSame('1500.00', $pos['amount']);
        self::assertFalse($pos['negative']);

        $neg = $this->parser->parseSigned('-2222222.22');
        self::assertSame('2222222.22', $neg['amount']);
        self::assertTrue($neg['negative']);
    }

    #[DataProvider('invalidAmounts')]
    public function test_rejects_invalid_amounts(mixed $raw): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->parser->parse($raw);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function invalidAmounts(): array
    {
        return [
            'empty' => [''],
            'null' => [null],
            'paren' => ['(100)'],
            'scientific' => ['1e3'],
            'junk' => ['abc'],
            'ambiguous dot' => ['1.5000'],
            'too many decimals' => ['1.2345'],
        ];
    }
}
