<?php

namespace AMToolkit\Modules\Courses\Domain;

defined('ABSPATH') || exit;

final class Mp4StreamabilityInspector
{
    private const HEADER_SIZE = 8;
    private const EXTENDED_HEADER_SIZE = 16;
    private const MAX_ATOMS = 10000;

    public function inspect(string $path): bool|\WP_Error
    {
        $resourceSize = filesize($path);
        $stream = fopen($path, 'rb');

        if ($resourceSize === false || $resourceSize < self::HEADER_SIZE || $stream === false) {
            return $this->invalid();
        }

        $offset = 0;
        $firstMediaDataOffset = null;

        for ($atomCount = 0; $atomCount < self::MAX_ATOMS && $offset < $resourceSize; $atomCount++) {
            if (fseek($stream, $offset) !== 0) {
                fclose($stream);
                return $this->invalid();
            }

            $header = fread($stream, self::HEADER_SIZE);

            if ($header === false || strlen($header) !== self::HEADER_SIZE) {
                fclose($stream);
                return $this->invalid();
            }

            $atomSize = $this->unsignedInt32(substr($header, 0, 4));
            $atomType = substr($header, 4, 4);
            $headerSize = self::HEADER_SIZE;

            if ($atomCount === 0 && $atomType !== 'ftyp') {
                fclose($stream);
                return $this->invalid();
            }

            if ($atomSize === 1) {
                $extendedSize = fread($stream, 8);

                if ($extendedSize === false || strlen($extendedSize) !== 8) {
                    fclose($stream);
                    return $this->invalid();
                }

                $atomSize = $this->unsignedInt64($extendedSize);
                $headerSize = self::EXTENDED_HEADER_SIZE;
            } elseif ($atomSize === 0) {
                $atomSize = $resourceSize - $offset;
            }

            if ($atomSize < $headerSize || $atomSize > $resourceSize - $offset) {
                fclose($stream);
                return $this->invalid();
            }

            if ($atomType === 'mdat' && $firstMediaDataOffset === null) {
                $firstMediaDataOffset = $offset;
            }

            if ($atomType === 'moov') {
                fclose($stream);
                return $firstMediaDataOffset === null || $offset < $firstMediaDataOffset;
            }

            $offset += $atomSize;
        }

        fclose($stream);
        return $this->invalid();
    }

    private function unsignedInt32(string $bytes): int
    {
        $value = unpack('Nvalue', $bytes);

        return is_array($value) ? (int) $value['value'] : 0;
    }

    private function unsignedInt64(string $bytes): int
    {
        if (PHP_INT_SIZE < 8) {
            return 0;
        }

        $value = unpack('Nhigh/Nlow', $bytes);

        if (!is_array($value)) {
            return 0;
        }

        $high = (int) $value['high'];
        $low = (int) $value['low'];

        if ($high > intdiv(PHP_INT_MAX - $low, 4294967296)) {
            return 0;
        }

        return ($high * 4294967296) + $low;
    }

    private function invalid(): \WP_Error
    {
        return new \WP_Error(
            'am_toolkit_course_video_invalid_mp4',
            __('Nie udało się zweryfikować struktury nagrania MP4.', 'am-toolkit')
        );
    }
}
