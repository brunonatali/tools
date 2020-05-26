<?php declare(strict_types=1);

namespace BrunoNatali\Tools;


class DataManipulation implements DataManipulationInterface
{

    private $buffer = null; 

    /*
        Simple return len in the beggining
    */
    public static function simpleSerialEncode($data): string
    {
        return \str_pad((string) \strlen($data), self::STRING_LEN, "0", STR_PAD_LEFT) . $data;
    }

    /**
     * $len will got current string len
    */
    public function simpleSerialDecode($data = null, &$len = null): string
    {
        if ($data === null) {
            if ($this->buffer === null)
                return null;

            $len = 0;
            $dataToReturn = $this->simpleSerialDecode($this->buffer, $len);
            $currentParsedLen = $len + self::STRING_LEN;
            if (\strlen($this->buffer) - $currentParsedLen)
                $this->buffer = \substr($this->buffer, $currentParsedLen);
            else
                $this->buffer = null;

            return $dataToReturn;
        } else {
            $originalDataLen = \strlen($data);
            $len = (int) \substr($data, 0, self::STRING_LEN);
            if ($len === 0) return null;

            $dataToReturn = \substr($data, self::STRING_LEN, $len);

            if (($len = \strlen($dataToReturn)) !== $originalDataLen)
                $this->buffer .= \substr($data, self::STRING_LEN + $len);

            return $dataToReturn;
        }
    }
}