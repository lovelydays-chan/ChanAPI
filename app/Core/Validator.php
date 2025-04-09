<?php

namespace App\Core;

use App\Core\Database;

class Validator
{
    public static function validate(array $data, array $rules, array $messages = [])
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $rulesArray = explode('|', $ruleString);

            foreach ($rulesArray as $rule) {
                $value = $data[$field] ?? null;

                // required
                if ($rule === 'required' && empty($value)) {
                    $errors[$field][] = $messages["$field.required"] ?? "$field is required.";
                }

                // min:3
                if (str_starts_with($rule, 'min:')) {
                    $min = (int) str_replace('min:', '', $rule);
                    if (strlen($value) < $min) {
                        $errors[$field][] = $messages["$field.min"] ?? "$field must be at least $min characters.";
                    }
                }

                // max:50
                if (str_starts_with($rule, 'max:')) {
                    $max = (int) str_replace('max:', '', $rule);
                    if (strlen($value) > $max) {
                        $errors[$field][] = $messages["$field.max"] ?? "$field must not exceed $max characters.";
                    }
                }

                // email
                if ($rule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = $messages["$field.email"] ?? "$field must be a valid email address.";
                }

                // unique:users,email
                if (str_starts_with($rule, 'unique:')) {
                    [$table, $column] = explode(',', str_replace('unique:', '', $rule));
                    if (self::isDuplicate($table, $column, $value)) {
                        $errors[$field][] = $messages["$field.unique"] ?? "$field has already been taken.";
                    }
                }
            }
        }

        // ถ้ามี error
        if (!empty($errors)) {
            $response = response();

            if ($response->isTesting()) {
                $response->setValidationErrors($errors);
                return $response->getTestResponse();
            }

            $response->validationErrors($errors)->send();
        }
    }

    /**
     * ตรวจสอบความซ้ำซ้อนในฐานข้อมูล
     */
    protected static function isDuplicate(string $table, string $column, $value): bool
    {
        if (empty($value)) {
            return false;
        }

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = :value");
        $stmt->execute(['value' => $value]);
        return $stmt->fetchColumn() > 0;
    }
}
