<?php

namespace Database\Seeders\Support;

use RuntimeException;
use UnexpectedValueException;

final class LegacyCustomerSqlReader
{
    /**
     * Read customer rows from a phpMyAdmin/MySQL dump without connecting to a database.
     *
     * @return array<int, array<string, string|null>>
     */
    public function read(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("No se puede leer el respaldo de clientes: {$path}");
        }

        $sql = file_get_contents($path);

        if ($sql === false) {
            throw new RuntimeException("No se pudo cargar el respaldo de clientes: {$path}");
        }

        preg_match_all(
            '/INSERT\s+INTO\s+`customers`\s*\((?<columns>[^)]+)\)\s*VALUES\s*/i',
            $sql,
            $statements,
            PREG_OFFSET_CAPTURE
        );

        if (empty($statements[0])) {
            throw new UnexpectedValueException('El respaldo no contiene instrucciones INSERT para customers.');
        }

        $records = [];

        foreach ($statements[0] as $index => $statement) {
            preg_match_all('/`([^`]+)`/', $statements['columns'][$index][0], $columnMatches);
            $columns = $columnMatches[1];
            $valuesOffset = $statement[1] + strlen($statement[0]);

            foreach ($this->parseValues($sql, $valuesOffset) as $row) {
                if (count($row) !== count($columns)) {
                    throw new UnexpectedValueException(sprintf(
                        'Fila inválida en customers.sql: se esperaban %d columnas y se encontraron %d.',
                        count($columns),
                        count($row)
                    ));
                }

                $records[] = array_combine($columns, $row);
            }
        }

        return $records;
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function parseValues(string $sql, int $offset): array
    {
        $rows = [];
        $row = [];
        $value = '';
        $inRow = false;
        $inQuote = false;
        $quotedValue = false;
        $length = strlen($sql);

        for ($position = $offset; $position < $length; $position++) {
            $character = $sql[$position];

            if (! $inRow) {
                if ($character === '(') {
                    $inRow = true;
                    $row = [];
                    $value = '';
                    $quotedValue = false;
                } elseif ($character === ';') {
                    break;
                }

                continue;
            }

            if ($inQuote) {
                if ($character === '\\') {
                    $position++;
                    $value .= $this->decodeEscape($sql[$position] ?? '');

                    continue;
                }

                if ($character === "'") {
                    if (($sql[$position + 1] ?? '') === "'") {
                        $value .= "'";
                        $position++;

                        continue;
                    }

                    $inQuote = false;

                    continue;
                }

                $value .= $character;

                continue;
            }

            if ($character === "'") {
                $inQuote = true;
                $quotedValue = true;

                continue;
            }

            if ($character === ',') {
                $row[] = $this->castValue($value, $quotedValue);
                $value = '';
                $quotedValue = false;

                continue;
            }

            if ($character === ')') {
                $row[] = $this->castValue($value, $quotedValue);
                $rows[] = $row;
                $inRow = false;

                continue;
            }

            $value .= $character;
        }

        if ($inQuote || $inRow) {
            throw new UnexpectedValueException('El respaldo de clientes termina dentro de una fila o cadena SQL.');
        }

        return $rows;
    }

    private function castValue(string $value, bool $quoted): ?string
    {
        $value = $quoted ? $value : trim($value);

        if (! $quoted && strcasecmp($value, 'NULL') === 0) {
            return null;
        }

        return $value;
    }

    private function decodeEscape(string $character): string
    {
        return match ($character) {
            '0' => "\0",
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            'Z' => chr(26),
            default => $character,
        };
    }
}
