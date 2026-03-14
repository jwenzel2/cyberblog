<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class CborDecoder
{
    public function decode(string $data): mixed
    {
        $offset = 0;
        return $this->read($data, $offset);
    }

    private function read(string $data, int &$offset): mixed
    {
        $initial = ord($data[$offset++]);
        $major = $initial >> 5;
        $additional = $initial & 0x1f;
        $length = $this->readLength($data, $offset, $additional);

        return match ($major) {
            0 => $length,
            1 => -1 - $length,
            2 => $this->readBytes($data, $offset, $length),
            3 => $this->readBytes($data, $offset, $length),
            4 => $this->readArray($data, $offset, $length),
            5 => $this->readMap($data, $offset, $length),
            default => throw new RuntimeException('Unsupported CBOR major type: ' . $major),
        };
    }

    private function readLength(string $data, int &$offset, int $additional): int
    {
        return match (true) {
            $additional < 24 => $additional,
            $additional === 24 => ord($data[$offset++]),
            $additional === 25 => unpack('n', $this->readBytes($data, $offset, 2))[1],
            $additional === 26 => unpack('N', $this->readBytes($data, $offset, 4))[1],
            default => throw new RuntimeException('Unsupported CBOR length encoding'),
        };
    }

    private function readBytes(string $data, int &$offset, int $length): string
    {
        $value = substr($data, $offset, $length);
        $offset += $length;
        return $value;
    }

    private function readArray(string $data, int &$offset, int $length): array
    {
        $array = [];
        for ($i = 0; $i < $length; $i++) {
            $array[] = $this->read($data, $offset);
        }
        return $array;
    }

    private function readMap(string $data, int &$offset, int $length): array
    {
        $map = [];
        for ($i = 0; $i < $length; $i++) {
            $key = $this->read($data, $offset);
            $map[$key] = $this->read($data, $offset);
        }
        return $map;
    }
}
