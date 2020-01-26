<?php

namespace DHGenerator;

/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * @package     DH Generator
 * @copyright   2019 - 2020 Podvirnyy Nikita (Observer KRypt0n_)
 * @license     GNU GPLv3 <https://www.gnu.org/licenses/gpl-3.0.html>
 * @author      Podvirnyy Nikita (Observer KRypt0n_)
 * 
 * Contacts:
 *
 * Email: <suimin.tu.mu.ga.mi@gmail.com>
 * VK:    vk.com/technomindlp
 *        vk.com/hphp_convertation
 * 
 */

class Generator
{
    public $g;
    public $p;
    public $num;

    public $alpha;

    protected $bcmathEnabled = false;
    protected $gmpEnabled = false;

    /**
     * Конструктор генератора
     * 
     * @param int $g - первое общее число
     * @param int $p - второе общее число
     * [@param int $num = null] - большое случайное число (задаётся автоматически если не указано)
     * 
     * @throws \Exception - выбрасывает исключение если указанные параметры < 1
     * 
     * @example
     * 
     * $A = new Generator (2348, 24723847);
     * $B = new Generator (2348, 24723847);
     * 
     * echo 'A: '. $A->generate ($B->getAlpha ()) . PHP_EOL;
     * echo 'B: '. $B->generate ($A->getAlpha ());
     */
    public function __construct (int $g, int $p, int $num = null)
    {
        if ($g < 1 || $p < 1 || ($num !== null && $num < 1))
            throw new \Exception ('Generator params must be upper than 1');

        $this->g = $g;
        $this->p = $p;
        
        $this->bcmathEnabled = extension_loaded ('bcmath');
        $this->gmpEnabled    = extension_loaded ('gmp');

        $this->num = $num ?: random_int (100000, ($this->bcmathEnabled || $this->gmpEnabled) ? 999999999 : 999999);

        if ($this->bcmathEnabled)
            $this->alpha = (int) bcpowmod ((string) $g, (string) $this->num, (string) $p);

        elseif ($this->gmpEnabled)
            $this->alpha = (int) gmp_strval (gmp_powm ((string) $g, (string) $this->num, (string) $p));

        else $this->alpha = $this->powmod ($g, $this->num, $p);
    }

    /**
     * Получение параметра alpha
     * 
     * @return int
     */
    public function getAlpha (): int
    {
        return $this->alpha;
    }

    /**
     * Генерация общего секретного числа
     * 
     * @param int $alpha - alpha-параметр генератора второго клиента
     * 
     * @return int - возвращает общий секретный ключ
     * 
     * @throws \Exception - выбрасывает исключение если $alpha < 1
     */
    public function generate (int $alpha): int
    {
        if ($alpha < 1)
            throw new \Exception ('$alpha param must be upper than 1');
        
        if ($this->bcmathEnabled)
            return (int) bcpowmod ((string) $alpha, (string) $this->num, (string) $this->p);

        elseif ($this->gmpEnabled)
            return (int) gmp_strval (gmp_powm ((string) $alpha, (string) $this->num, (string) $this->p));

        else return $this->powmod ($alpha, $this->num, $this->p);
    }

    /**
     * Возведение в степень по модулю
     * 
     * @param int $base - число
     * @param int $exp  - степень
     * @param int $mod  - модуль
     * 
     * @return int
     */
    protected function powmod (int $base, int $exp, int $mod): int
    {
        $result = 1;

        while ($exp > 0)
        {
            if (($exp % 2) == 1)
                $result = ($result * $base) % $mod;
            
            $base = ($base * $base) % $mod;
            $exp  = floor ($exp / 2);
        }

        return $result;
    }
}
