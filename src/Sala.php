<?php

declare(strict_types=1);

namespace Alfa;

#[Table(name:'sala')]
class Sala extends Entity
{
    #[PrimaryKey]
    #[Column]
    public int $idsala;

    #[Column]
    public string $descricao;

    #[Column]
    public string $dataatualizacao;

}