<?php

namespace App\Services\Payments;

class IkeePayCatalog
{
    /**
     * @return array<string, string>
     */
    public function operators(): array
    {
        $configured = config('ikeepay.operators', []);
        if (! is_array($configured)) {
            return [];
        }

        $operators = [];
        foreach ($configured as $code => $label) {
            if (! is_string($code) || ! is_string($label)) {
                continue;
            }

            $code = strtoupper(trim($code));
            if ($code !== '') {
                $operators[$code] = $label;
            }
        }

        return $operators;
    }

    /**
     * @return array<string, list<string>>
     */
    public function countries(): array
    {
        $configured = config('ikeepay.countries', []);
        if (! is_array($configured)) {
            return [];
        }

        $countries = [];
        foreach ($configured as $country => $operators) {
            if (! is_string($country) || ! is_array($operators)) {
                continue;
            }

            $country = strtoupper(trim($country));
            if (! preg_match('/^[A-Z]{2}$/', $country)) {
                continue;
            }

            $allowed = [];
            foreach ($operators as $operator) {
                if (! is_string($operator)) {
                    continue;
                }

                $operator = strtoupper(trim($operator));
                if (array_key_exists($operator, $this->operators())) {
                    $allowed[] = $operator;
                }
            }

            if ($allowed !== []) {
                $countries[$country] = array_values(array_unique($allowed));
            }
        }

        return $countries;
    }

    /**
     * @return list<array{country: string, operator: string, label: string}>
     */
    public function choices(): array
    {
        $choices = [];
        foreach ($this->countries() as $country => $operators) {
            foreach ($operators as $operator) {
                $choices[] = [
                    'country' => $country,
                    'operator' => $operator,
                    'label' => $this->operators()[$operator],
                ];
            }
        }

        return $choices;
    }

    public function allows(string $country, string $operator): bool
    {
        $country = strtoupper(trim($country));
        $operator = strtoupper(trim($operator));
        $operators = $this->countries()[$country] ?? [];

        return in_array($operator, $operators, true);
    }
}
