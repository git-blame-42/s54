<?php

namespace ${NAMESPACE};

use Core\Database\ActiveRecord\Model;
use Lib\Validations;

/**
 * @property int ${DS}id
 * @property string ${DS}${coluna}
 */
class ${NAME} extends Model
{
    protected static string ${DS}table = '${tabela}';
    protected static array ${DS}columns = [
        '${coluna}',
    ];

    public function validates(): void
    {
        Validations::notEmpty('${coluna}', ${DS}this);
    }
}
