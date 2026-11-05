<?php

namespace Tests\Unit\Core\Database\ActiveRecord;

use Core\Database\ActiveRecord\Model;
use Lib\Validations;

/**
 * Model de teste que NÃO sobrescreve beforeValidation(): usa o gancho vazio do core.
 *
 * @property string|null $name
 */
class PlainModel extends Model
{
    protected static string $table = 'plain_models';
    protected static array $columns = ['name'];

    public function validates(): void
    {
        Validations::notEmpty('name', $this);
    }
}
