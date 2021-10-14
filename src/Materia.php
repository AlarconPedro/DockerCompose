<?php

declare(strict_types=1);

namespace Alfa;

#[Table(name:'materia')]
class Materia extends Entity
{
    #[PrimaryKey]
    #[Column]
    public int $id;
    #[Column]
    public string $dataatualizacao;
    #[Column]
    public string $nome;
    #[Column]
    public int $dia;
    #[Column]
    public string $horario;

    public string $codigoUnico = "";
}