<?php

namespace App\Services\System;

/**
 * Hochperformanter ITU-T G.711 mu-Law <-> 16-Bit Little-Endian PCM Audio-Codec
 * für die Echtzeit-Telefonie zwischen Twilio Media Streams (8 kHz mu-Law)
 * und Google Gemini 3.1 Flash Live (16 kHz Input / 24 kHz Output PCM).
 */
class AudioMulawCodec
{
    private const BIAS = 0x84; // 132
    private const CLIP = 32635;

    /** @var array<int, int>|null 0..255 -> signed int16 (-32124..32124) */
    private static ?array $mulawToInt = null;

    /** @var string|null 65536-Byte Lookup-String: Index ($pcm + 32768) -> 1 Byte mu-Law */
    private static ?string $pcmToMulaw = null;

    private static function ensureTables(): void
    {
        if (self::$mulawToInt !== null && self::$pcmToMulaw !== null) {
            return;
        }

        $decode = [];
        for ($b = 0; $b < 256; $b++) {
            $decode[$b] = self::decodeMulawByte($b);
        }
        self::$mulawToInt = $decode;

        $encodeChunks = [];
        for ($s = -32768; $s <= 32767; $s++) {
            $encodeChunks[] = chr(self::encodePcmSample($s));
        }
        self::$pcmToMulaw = implode('', $encodeChunks);
    }

    /**
     * Dekodiert ein einzelnes 8-Bit ITU-T G.711 mu-Law Byte in ein vorzeichenbehaftetes 16-Bit PCM-Sample.
     */
    public static function decodeMulawByte(int $mulawByte): int
    {
        $u = (~$mulawByte) & 0xFF;
        $sign = $u & 0x80;
        $exponent = ($u >> 4) & 0x07;
        $mantissa = $u & 0x0F;
        $magnitude = ((($mantissa << 3) + self::BIAS) << $exponent) - self::BIAS;

        return $sign !== 0 ? -$magnitude : $magnitude;
    }

    /**
     * Kodiert ein vorzeichenbehaftetes 16-Bit PCM-Sample (-32768..32767) in ein 8-Bit ITU-T G.711 mu-Law Byte.
     */
    public static function encodePcmSample(int $pcm16): int
    {
        if ($pcm16 < 0) {
            $pcm16 = -$pcm16;
            $sign = 0x80;
        } else {
            $sign = 0x00;
        }

        if ($pcm16 > self::CLIP) {
            $pcm16 = self::CLIP;
        }

        $pcm16 += self::BIAS;

        $exponent = 7;
        for ($expMask = 0x4000; ($pcm16 & $expMask) === 0 && $exponent > 0; $exponent--, $expMask >>= 1) {
        }

        $mantissa = ($pcm16 >> ($exponent + 3)) & 0x0F;

        return (~($sign | ($exponent << 4) | $mantissa)) & 0xFF;
    }

    /**
     * Wandelt eingehendes 8 kHz G.711 mu-Law Audio von Twilio per linearer Interpolation
     * nahtlos (ohne Paketgrenzen-Knacken) in 16 kHz 16-Bit Little-Endian PCM für Gemini Live um.
     */
    public static function mulaw8kToPcm16k(string $mulawBytes, int &$prevSample = 0): string
    {
        $len = strlen($mulawBytes);
        if ($len === 0) {
            return '';
        }

        self::ensureTables();
        $decode = self::$mulawToInt;

        $out = [];
        for ($i = 0; $i < $len; $i++) {
            $cur = $decode[ord($mulawBytes[$i])];
            $mid = ($prevSample + $cur) >> 1;
            $out[] = pack('vv', $mid & 0xFFFF, $cur & 0xFFFF);
            $prevSample = $cur;
        }

        return implode('', $out);
    }

    /**
     * Wandelt ausgehendes 24 kHz 16-Bit Little-Endian PCM Audio von Gemini Live (Aoede)
     * mittels 3-Tap Tiefpassfilter [0.25, 0.5, 0.25] und 3:1 Dezimierung in glasklares
     * 8 kHz G.711 mu-Law Audio für Twilio um. Rest-Bytes an Chunk-Grenzen bleiben in $carryBytes erhalten.
     */
    public static function pcm24kToMulaw8k(string $pcm24kBytes, string &$carryBytes = ''): string
    {
        $buf = $carryBytes !== '' ? ($carryBytes . $pcm24kBytes) : $pcm24kBytes;
        $totalBytes = strlen($buf);
        if ($totalBytes < 6) {
            $carryBytes = $buf;
            return '';
        }

        self::ensureTables();
        $encodeMap = self::$pcmToMulaw;

        $triplets = intdiv($totalBytes, 6);
        $consumedBytes = $triplets * 6;
        $carryBytes = $consumedBytes < $totalBytes ? substr($buf, $consumedBytes) : '';

        $out = str_repeat("\xFF", $triplets);
        $offset = 0;

        for ($t = 0; $t < $triplets; $t++) {
            $u0 = ord($buf[$offset]) | (ord($buf[$offset + 1]) << 8);
            $s0 = $u0 >= 0x8000 ? $u0 - 0x10000 : $u0;

            $u1 = ord($buf[$offset + 2]) | (ord($buf[$offset + 3]) << 8);
            $s1 = $u1 >= 0x8000 ? $u1 - 0x10000 : $u1;

            $u2 = ord($buf[$offset + 4]) | (ord($buf[$offset + 5]) << 8);
            $s2 = $u2 >= 0x8000 ? $u2 - 0x10000 : $u2;

            $offset += 6;

            $filtered = ($s0 + ($s1 << 1) + $s2) >> 2;
            if ($filtered < -32768) {
                $filtered = -32768;
            } elseif ($filtered > 32767) {
                $filtered = 32767;
            }

            $out[$t] = $encodeMap[$filtered + 32768];
        }

        return $out;
    }
}
