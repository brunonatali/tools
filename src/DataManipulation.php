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
    public function simpleSerialDecode($data = null, &$len = null)
    {
        if ($data === null) { // Get buffer
            if ($this->buffer === null)
                return null;

            $bufferLen = \strlen($this->buffer);
            // Get next package len
            $len = (int) \substr($this->buffer, 0, self::STRING_LEN);
            if ($len === 0) { // Empty package
                $this->buffer = null;
                return null;
            }

            // Get package
            $dataToReturn = \substr($this->buffer, self::STRING_LEN, $len);

            // Check if have more packages on buffer
            if ((\strlen($dataToReturn) + self::STRING_LEN) !== $bufferLen)
                $this->buffer = \substr($this->buffer, self::STRING_LEN + $len);
            else
                $this->buffer = null;

            return $dataToReturn;
        } else {
            if ($this->buffer !== null) {
                // First remove next package
                $dataToReturn = $this->simpleSerialDecode();
                // Add current data to buffer
                $this->buffer .= $data;

                return $dataToReturn;
            }

            $originalDataLen = \strlen($data);
            // Get package len
            $len = (int) \substr($data, 0, self::STRING_LEN);
            if ($len === 0) 
                return null;

            // Get package
            $dataToReturn = \substr($data, self::STRING_LEN, $len);

            // Check if have more packages & add to buffer
            if ((\strlen($dataToReturn) + self::STRING_LEN) !== $originalDataLen)
                $this->buffer .= \substr($data, self::STRING_LEN + $len);

            return $dataToReturn;
        }
    }
}