<?php

namespace App\Core;

use App\Core\Database;
use App\Exceptions\ValidationException;

class Validator
{
    public static bool $testMode = false;
    protected static array $errors = [];
    public static function validate(array $data, array $rules, array $messages = []): void
    {
        self::$errors = [];
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Validation data must be an array');
        }
        foreach ($rules as $field => $ruleString) {
            $rulesArray = explode('|', $ruleString);

            foreach ($rulesArray as $rule) {
                $value = $data[$field] ?? null;

                // required
                if ($rule === 'required' && empty($value)) {
                    self::$errors[$field][] = $messages["$field.required"] ?? "$field is required.";
                }

                if (str_starts_with($rule, 'min:')) {
                    $min = (int) str_replace('min:', '', $rule);
                    if (!is_null($value) && strlen((string) $value) < $min) {
                        self::$errors[$field][] = $messages["$field.min"] ?? "$field must be at least $min characters.";
                    }
                }

                if (str_starts_with($rule, 'max:')) {
                    $max = (int) str_replace('max:', '', $rule);
                    if (!is_null($value) && strlen((string) $value) > $max) {
                        self::$errors[$field][] = $messages["$field.max"] ?? "$field must not exceed $max characters.";
                    }
                }

                // email
                if ($rule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    self::$errors[$field][] = $messages["$field.email"] ?? "$field must be a valid email address.";
                }

                // unique:users,email
                if (str_starts_with($rule, 'unique:')) {
                    [$table, $column] = explode(',', str_replace('unique:', '', $rule));
                    if (self::isDuplicate($table, $column, $value)) {
                        self::$errors[$field][] = $messages["$field.unique"] ?? "$field has already been taken.";
                    }
                }
            }
        }

        if (!empty(self::$errors)) {
            if (response()->isTesting()) {
                throw new ValidationException(self::$errors);
            }
            response()->validationErrors(self::$errors)->send();
            exit; // หยุดการทำงานต่อเมื่อมี error
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
    public static function getLastErrors(): array
    {
        return self::$errors;
    }
}
