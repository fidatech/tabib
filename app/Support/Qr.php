<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * A small QR code maker for the address printed on a prescription: byte mode, error correction
 * level M, versions 1 to 10 (up to 213 bytes). Draws an SVG, so it prints sharp and needs no
 * picture file and no JavaScript.
 */
class Qr
{
    /** version => [data bytes it holds, error correction codewords per block, [[blocks, data codewords per block], ...]] (level M) */
    private const VERSIONS = [
        1 => [14, 10, [[1, 16]]],
        2 => [26, 16, [[1, 28]]],
        3 => [42, 26, [[1, 44]]],
        4 => [62, 18, [[2, 32]]],
        5 => [84, 24, [[2, 43]]],
        6 => [106, 16, [[4, 27]]],
        7 => [122, 18, [[4, 31]]],
        8 => [152, 22, [[2, 38], [2, 39]]],
        9 => [180, 22, [[3, 36], [2, 37]]],
        10 => [213, 26, [[4, 43], [1, 44]]],
    ];

    private const ALIGNMENT = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    /** @var array<int, array<int, ?bool>> */
    private array $modules = [];

    /** @var array<int, array<int, bool>> modules that are not data (finders, timing, format ...) */
    private array $reserved = [];

    private int $size = 0;

    /** The QR code of $text as an SVG, $pixels wide, with the 4-module quiet zone around it. */
    public static function svg(string $text, int $pixels = 120): string
    {
        $matrix = (new self)->make($text);
        $n = count($matrix);
        $full = $n + 8;
        $path = '';
        foreach ($matrix as $y => $row) {
            foreach ($row as $x => $dark) {
                if ($dark) {
                    $path .= 'M'.($x + 4).' '.($y + 4).'h1v1h-1z';
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$full.' '.$full.'" width="'.$pixels.'" height="'.$pixels.'" shape-rendering="crispEdges" role="img" aria-label="QR">'
            .'<rect width="100%" height="100%" fill="#fff"/><path d="'.$path.'" fill="#000"/></svg>';
    }

    /** @return array<int, array<int, bool>> the dark (true) and light modules, row by row */
    public function make(string $text): array
    {
        $length = strlen($text);
        $version = null;
        foreach (self::VERSIONS as $v => $spec) {
            if ($length <= $spec[0]) {
                $version = $v;
                break;
            }
        }
        if ($version === null) {
            throw new InvalidArgumentException('Too long for a QR code of this maker: '.$length.' bytes.');
        }

        $codewords = $this->codewords($text, $version);

        // the mask that leaves the fewest patterns a reader could mistake
        $best = null;
        $bestScore = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $this->base($version);
            $this->placeData($codewords, $mask);
            $this->placeFormat($mask);
            $score = $this->penalty();
            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $this->modules;
            }
        }

        return array_map(fn (array $row) => array_map(fn ($m) => (bool) $m, $row), $best);
    }

    /* ------------------------------------------------------------ the codewords */

    private function codewords(string $text, int $version): array
    {
        [, $ecPerBlock, $groups] = self::VERSIONS[$version];
        $dataTotal = 0;
        foreach ($groups as [$blocks, $per]) {
            $dataTotal += $blocks * $per;
        }

        // mode (byte), length, the bytes, then the end and the padding
        $bits = '0100'.str_pad(decbin(strlen($text)), $version < 10 ? 8 : 16, '0', STR_PAD_LEFT);
        foreach (str_split($text) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $capacity = $dataTotal * 8;
        $bits .= str_repeat('0', min(4, $capacity - strlen($bits)));
        if (strlen($bits) % 8) {
            $bits .= str_repeat('0', 8 - strlen($bits) % 8);
        }
        $data = array_map('bindec', str_split($bits, 8));
        for ($pad = 0; count($data) < $dataTotal; $pad++) {
            $data[] = $pad % 2 ? 0x11 : 0xEC;
        }

        // split into blocks, each with its error correction
        $blocksData = [];
        $blocksEc = [];
        $offset = 0;
        foreach ($groups as [$blocks, $per]) {
            for ($b = 0; $b < $blocks; $b++) {
                $chunk = array_slice($data, $offset, $per);
                $offset += $per;
                $blocksData[] = $chunk;
                $blocksEc[] = self::reedSolomon($chunk, $ecPerBlock);
            }
        }

        // interleave: the first codeword of every block, then the second ...
        $out = [];
        $longest = max(array_map('count', $blocksData));
        for ($i = 0; $i < $longest; $i++) {
            foreach ($blocksData as $block) {
                if (isset($block[$i])) {
                    $out[] = $block[$i];
                }
            }
        }
        for ($i = 0; $i < $ecPerBlock; $i++) {
            foreach ($blocksEc as $block) {
                $out[] = $block[$i];
            }
        }

        return $out;
    }

    /** The error correction codewords of $data over GF(256) with the polynomial 0x11D. */
    private static function reedSolomon(array $data, int $degree): array
    {
        static $exp = null, $log = null;
        if ($exp === null) {
            $exp = array_fill(0, 512, 0);
            $log = array_fill(0, 256, 0);
            $x = 1;
            for ($i = 0; $i < 255; $i++) {
                $exp[$i] = $x;
                $log[$x] = $i;
                $x <<= 1;
                if ($x & 0x100) {
                    $x ^= 0x11D;
                }
            }
            for ($i = 255; $i < 512; $i++) {
                $exp[$i] = $exp[$i - 255];
            }
        }
        $mul = fn (int $a, int $b) => $a === 0 || $b === 0 ? 0 : $exp[$log[$a] + $log[$b]];

        // the generator polynomial (x - a^0)(x - a^1)...(x - a^(degree-1))
        $gen = [1];
        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            foreach ($gen as $j => $coef) {
                $next[$j] ^= $coef;
                $next[$j + 1] ^= $mul($coef, $exp[$i]);
            }
            $gen = $next;
        }

        $rest = array_merge($data, array_fill(0, $degree, 0));
        for ($i = 0; $i < count($data); $i++) {
            $factor = $rest[$i];
            if ($factor !== 0) {
                foreach ($gen as $j => $coef) {
                    $rest[$i + $j] ^= $mul($coef, $factor);
                }
            }
        }

        return array_slice($rest, count($data));
    }

    /* ------------------------------------------------------------ the fixed patterns */

    private function base(int $version): void
    {
        $this->size = $version * 4 + 17;
        $this->modules = array_fill(0, $this->size, array_fill(0, $this->size, null));
        $this->reserved = array_fill(0, $this->size, array_fill(0, $this->size, false));

        foreach ([[0, 0], [$this->size - 7, 0], [0, $this->size - 7]] as [$x, $y]) {
            $this->finder($x, $y);
        }
        // timing lines
        for ($i = 8; $i < $this->size - 8; $i++) {
            $this->set($i, 6, $i % 2 === 0);
            $this->set(6, $i, $i % 2 === 0);
        }
        // alignment patterns, everywhere but on the three finders
        $positions = self::ALIGNMENT[$version];
        $last = count($positions) - 1;
        foreach ($positions as $i => $cy) {
            foreach ($positions as $j => $cx) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $last) || ($i === $last && $j === 0)) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $this->set($cx + $dx, $cy + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }
        // the dark module, and the places of the format information (filled later)
        $this->set(8, $this->size - 8, true);
        for ($i = 0; $i < 9; $i++) {
            $this->reserve(8, $i);
            $this->reserve($i, 8);
        }
        for ($i = 0; $i < 8; $i++) {
            $this->reserve($this->size - 1 - $i, 8);
            $this->reserve(8, $this->size - 1 - $i);
        }
        // version information (version 7 and up)
        if ($version >= 7) {
            $rem = $version;
            for ($i = 0; $i < 12; $i++) {
                $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
            }
            $bits = ($version << 12) | $rem;
            for ($i = 0; $i < 18; $i++) {
                $bit = (($bits >> $i) & 1) === 1;
                $a = intdiv($i, 3);
                $b = $i % 3 + $this->size - 11;
                $this->set($a, $b, $bit);
                $this->set($b, $a, $bit);
            }
        }
    }

    private function finder(int $x, int $y): void
    {
        for ($dy = -1; $dy <= 7; $dy++) {
            for ($dx = -1; $dx <= 7; $dx++) {
                $xx = $x + $dx;
                $yy = $y + $dy;
                if ($xx < 0 || $yy < 0 || $xx >= $this->size || $yy >= $this->size) {
                    continue;
                }
                $inside = $dx >= 0 && $dx <= 6 && $dy >= 0 && $dy <= 6;
                $dark = $inside && ($dx === 0 || $dx === 6 || $dy === 0 || $dy === 6 || ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4));
                $this->set($xx, $yy, $dark);
            }
        }
    }

    private function set(int $x, int $y, bool $dark): void
    {
        $this->modules[$y][$x] = $dark;
        $this->reserved[$y][$x] = true;
    }

    private function reserve(int $x, int $y): void
    {
        $this->reserved[$y][$x] = true;
        $this->modules[$y][$x] ??= false;
    }

    /* ------------------------------------------------------------ data, masks, format */

    private function placeData(array $codewords, int $mask): void
    {
        $bits = [];
        foreach ($codewords as $cw) {
            for ($i = 7; $i >= 0; $i--) {
                $bits[] = (($cw >> $i) & 1) === 1;
            }
        }
        $index = 0;
        $upward = true;
        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;   // the vertical timing line is skipped
            }
            for ($step = 0; $step < $this->size; $step++) {
                $y = $upward ? $this->size - 1 - $step : $step;
                for ($col = 0; $col < 2; $col++) {
                    $x = $right - $col;
                    if ($this->reserved[$y][$x]) {
                        continue;
                    }
                    $dark = $bits[$index] ?? false;
                    $index++;
                    if (self::maskBit($mask, $x, $y)) {
                        $dark = ! $dark;
                    }
                    $this->modules[$y][$x] = $dark;
                }
            }
            $upward = ! $upward;
        }
    }

    private static function maskBit(int $mask, int $x, int $y): bool
    {
        return match ($mask) {
            0 => ($x + $y) % 2 === 0,
            1 => $y % 2 === 0,
            2 => $x % 3 === 0,
            3 => ($x + $y) % 3 === 0,
            4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
            5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
            6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
            default => (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0,
        };
    }

    private function placeFormat(int $mask): void
    {
        $data = (0b00 << 3) | $mask;   // level M is 00
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;
        $bit = fn (int $i) => (($bits >> $i) & 1) === 1;

        // around the top-left finder
        for ($i = 0; $i <= 5; $i++) {
            $this->modules[$i][8] = $bit($i);
        }
        $this->modules[7][8] = $bit(6);
        $this->modules[8][8] = $bit(7);
        $this->modules[8][7] = $bit(8);
        for ($i = 9; $i < 15; $i++) {
            $this->modules[8][14 - $i] = $bit($i);
        }
        // the copy beside the other two finders
        for ($i = 0; $i < 8; $i++) {
            $this->modules[8][$this->size - 1 - $i] = $bit($i);
        }
        for ($i = 8; $i < 15; $i++) {
            $this->modules[$this->size - 15 + $i][8] = $bit($i);
        }
        $this->modules[$this->size - 8][8] = true;
    }

    private function penalty(): int
    {
        $n = $this->size;
        $m = $this->modules;
        $score = 0;
        // runs of five or more of one colour, in rows and in columns
        for ($line = 0; $line < 2; $line++) {
            for ($a = 0; $a < $n; $a++) {
                $run = 1;
                for ($b = 1; $b < $n; $b++) {
                    $cur = $line ? $m[$b][$a] : $m[$a][$b];
                    $prev = $line ? $m[$b - 1][$a] : $m[$a][$b - 1];
                    if ($cur === $prev) {
                        $run++;
                        if ($run === 5) {
                            $score += 3;
                        } elseif ($run > 5) {
                            $score++;
                        }
                    } else {
                        $run = 1;
                    }
                }
            }
        }
        // 2x2 blocks of one colour
        for ($y = 0; $y < $n - 1; $y++) {
            for ($x = 0; $x < $n - 1; $x++) {
                if ($m[$y][$x] === $m[$y][$x + 1] && $m[$y][$x] === $m[$y + 1][$x] && $m[$y][$x] === $m[$y + 1][$x + 1]) {
                    $score += 3;
                }
            }
        }
        // what looks like a finder: 1011101 with four light on one side
        $patterns = [[true, false, true, true, true, false, true, false, false, false, false], [false, false, false, false, true, false, true, true, true, false, true]];
        for ($a = 0; $a < $n; $a++) {
            for ($b = 0; $b <= $n - 11; $b++) {
                foreach ($patterns as $p) {
                    $row = true;
                    $col = true;
                    for ($k = 0; $k < 11; $k++) {
                        $row = $row && $m[$a][$b + $k] === $p[$k];
                        $col = $col && $m[$b + $k][$a] === $p[$k];
                    }
                    $score += ($row ? 40 : 0) + ($col ? 40 : 0);
                }
            }
        }
        // the balance of dark and light
        $dark = 0;
        foreach ($m as $row) {
            foreach ($row as $v) {
                $dark += $v ? 1 : 0;
            }
        }
        $score += intdiv(abs($dark * 20 - $n * $n * 10), $n * $n) * 10;

        return $score;
    }
}
