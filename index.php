<?php

/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * @package     Bit Points Network
 * @copyright   2020 Podvirnyy Nikita (Observer KRypt0n_)
 * @license     GNU GPLv3 <https://www.gnu.org/licenses/gpl-3.0.html>
 * @author      Podvirnyy Nikita (Observer KRypt0n_)
 * 
 * Contacts:
 *
 * Email: <suimin.tu.mu.ga.mi@gmail.com>
 * VK:    <https://vk.com/technomindlp>
 *        <https://vk.com/hphp_convertation>
 * 
 */

namespace BPN;

const TRACKER_KEY = 'EFJI#*$&(*#WEF(@Q#)(DFJSAO(@#*$)REFSKE)UJ*#@(&$';

require 'ext/DH-Generator/Generator.php';
require 'php/User.php';
require 'php/Tracker.php';

if (!isset ($_GET['request']))
    die ();

(new Tracker)->update ()
    ->processRequest (substr ($_SERVER['REQUEST_URI'], 1));
