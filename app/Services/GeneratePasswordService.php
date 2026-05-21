<?php

namespace App\Services;

class GeneratePasswordService
{
    protected int $length;
    protected bool $add_dashes;
    protected string $available_sets;

    public function __construct()
    {
        $this->length = 12;
        $this->add_dashes = false;
        $this->available_sets = 'luds';
    }

    /**
     * @return string|false
     */
    public function generateStrongPassword()
    {
        $sets = array();
        if (strpos($this->available_sets, 'l') !== false) {
            $sets[] = 'abcdefghjkmnpqrstuvwxyz';
        }
        if (strpos($this->available_sets, 'u') !== false) {
            $sets[] = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        }
        if (strpos($this->available_sets, 'd') !== false) {
            $sets[] = '0123456789';
        }
        if (strpos($this->available_sets, 's') !== false) {
            $sets[] = '!@#$%&*?';
        }

        $all = '';
        $password = '';
        foreach ($sets as $set) {
            $password .= $set[array_rand(str_split($set))];
            $all .= $set;
        }

        $all = str_split($all);
        for ($i = 0; $i < $this->length - count($sets); $i++) {
            $password .= $all[array_rand($all)];
        }

        $password = str_shuffle($password);

        if (!$this->add_dashes) {
            return $password;
        }

        $dash_len = floor(sqrt($this->length));
        $dash_str = '';
        while (strlen($password) > $dash_len) {
            $dash_str .= substr($password, 0, $dash_len) . '-';
            $password = substr($password, $dash_len);
        }
        $dash_str .= $password;
        return $dash_str;
    }
}