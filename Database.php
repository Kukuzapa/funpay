<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        $i = 0; // обход по строке
        $j = 0; // обход по массиву данных
        $preFinQuery = '';

        // для начала заполним все шаблоны и создадим пред финальную строку с условными блоками
        while ($i < strlen($query)) {
            $templ = '';

            if ($query[$i] === '?') {
                $templ = $query[$i];
 
                if (in_array($query[$i + 1], ['d', 'f', 'a', '#'])) {
                    $templ .= $query[$i + 1];
                    $i++;
                } else if ($query[$i + 1] !== ' ') {
                    // Неизвестный модификатор
                    throw new Exception('Неизвестный модификатор'); 
                }

                if (!isset($args[$j])) {
                    throw new Exception('Параметров меньше чем шаблонов');
                }

                $preFinQuery .= $this->format($templ, $args[$j]);

                $j++;
            } else {
                $preFinQuery .= $query[$i];
            }
            
            
            $i++;
        }

        $i = 0;
        $fillCondition = false;
        $condition = '';
        $finQuery = '';
        
        // а вот теперь надо избавится от условных блоков
        while ($i < strlen($preFinQuery)) {
            
            if ($preFinQuery[$i] === '{') {
                $fillCondition = true;
            }

            if ($fillCondition) {
                $condition .= $preFinQuery[$i];
            } else {
                $finQuery .= $preFinQuery[$i];
            }

            if ($preFinQuery[$i] === '}') {
                if (!strripos($condition, $this->skip())) {
                    $finQuery .= substr(substr($condition, 1), 0, -1);
                }

                $fillCondition = true;

                $condition = '';
            }

            $i++;
        }

        return $finQuery;
    }

    public function format($templ, $data)
    {
        if ($data === $this->skip()) {
            return $data;
        }

        $result = '';
        $type = gettype($data);

        if ($templ === '?') {
            if (!in_array($type, ['string', 'integer', 'double', 'boolean', 'NULL'])) {
                throw new Exception('Неправильный тип');
            }

            if (in_array($type, ['integer', 'double'])) {
                $result = (string)$data;
            } else if ($type === 'string') {
                $result = "'" . $data . "'";
                $result = addcslashes($result, "'");
            } else if ($type === 'boolean') {
                $result = $data ? '1' : '0';
            } else {
                $result = 'NULL';
            }
        }

        if ($templ === '?#') {
            if (!in_array($type, ['string', 'array'])) {
                throw new Exception('Неправильный тип');
            }

            if ($type === 'string') {
                $data = [ $data ];
            }

            foreach ($data as $value) {
                if (gettype($value) !== 'string') {
                    throw new Exception('Это не строка');
                } 

                $result .= '`' . $value . '`' . ', ';
            }

            $result = substr($result, 0, -2);
        }

        if ($templ === '?d') {
            if (!in_array($type, ['integer', 'boolean', 'NULL'])) {
                throw new Exception('Неправильный тип');
            }

            if ($type === 'integer') {
                $result = (string)$data;
            } else if ($type === 'boolean') {
                $result = $data ? '1' : '0';
            } else {
                $result = 'NULL';
            }
        }

        if ($templ === '?f') {
            if (!in_array($type, ['double', 'boolean', 'NULL'])) {
                throw new Exception('Неправильный тип');
            }

            if ($type === 'double') {
                $result = (string)$data;
            } else if ($type === 'boolean') {
                $result = $data ? '1' : '0';
            } else {
                $result = 'NULL';
            }
        }

        if ($templ === '?a') {
            if ($type !== 'array') {
                throw new Exception('Неправильный тип');
            }
            
            foreach ($data as $key=>$value) {
                if (gettype($key) === 'string') {
                    $result .= '`' . $key . '` = ';
                }

                $type = gettype($value);

                if (!in_array($type, ['string', 'integer', 'double', 'boolean', 'NULL'])) {
                    throw new Exception('TEST');
                }

                if (in_array($type, ['integer', 'double'])) {
                    $result .= (string)$value;
                } else if ($type === 'string') {
                    $result = "'" . $value . "'";
                    $result = addcslashes($result, "'");
                } else if ($type === 'boolean') {
                    $result .= $value ? '1' : '0';
                } else {
                    $result .= 'NULL';
                }

                $result .= ', ';
            }

            $result = substr($result, 0, -2);
            
        }

        return $result;
    }

    public function skip()
    {
        return '!!!!!';
    }
}
