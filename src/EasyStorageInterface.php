<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

interface EasyStorageInterface
{
    Const AS_JSON = 0x01;

    Const IDENTIFIER_VAR = '$identifier';
    Const DEFAULT_IDENTIFIER = '$fileName_$arrayName';
    Const EQUAL = ' = ';
    Const SEMICOLON = ';';
}
