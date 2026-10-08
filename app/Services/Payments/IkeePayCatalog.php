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
                'label' => $this->shopLabel($operator),
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

    /**
     * Ancien libellé de la boutique → code opérateur H2H déjà présent dans la configuration.
     * Un code absent de cette configuration n'est jamais renvoyé.
     *
     * @return array<string, string>
     */
    public function providerOperators(): array
    {
        $known = $this->operators();
        $map = [
            'airtel_money' => 'AIRTEL',
            'orange_money' => 'ORANGE',
        ];

        return array_filter(
            $map,
            fn (string $operator): bool => array_key_exists($operator, $known),
        );
    }

    /**
     * @return array{country: string, operator: string, label: string}|null
     */
    public function h2hChoiceFor(string $provider): ?array
    {
        $operator = $this->providerOperators()[$provider] ?? null;
        if ($operator === null) {
            return null;
        }

        $countries = [];
        foreach ($this->countries() as $country => $operators) {
            if (in_array($operator, $operators, true)) {
                $countries[] = $country;
            }
        }

        if (count($countries) !== 1) {
            return null;
        }

        return [
            'country' => $countries[0],
            'operator' => $operator,
            'label' => $this->shopLabel($operator),
        ];
    }

    public function shopLabel(string $operator): string
    {
        return match ($operator) {
            'AIRTEL' => 'Airtel Money',
            'ORANGE' => 'Orange Money',
            default => $this->operators()[$operator] ?? $operator,
        };
    }
}
