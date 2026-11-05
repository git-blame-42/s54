<?php

namespace Tests\Unit\Core\Database\ActiveRecord;

use Core\Database\ActiveRecord\Model;
use Lib\Validations;

/**
 * Model de teste que sobrescreve o gancho beforeValidation() e registra a ordem das chamadas.
 *
 * @property string|null $name
 */
class TrimmingModel extends Model
{
    protected static string $table = 'trimming_models';
    protected static array $columns = ['name'];

    /** @var array<int, string> */
    public array $calls = [];

    protected function beforeValidation(): void
    {
        $this->calls[] = 'beforeValidation';

        if (is_string($this->name)) {
            $this->name = trim($this->name);
        }
    }

    public function validates(): void
    {
        $this->calls[] = 'validates';

        Validations::notEmpty('name', $this);
    }
}
