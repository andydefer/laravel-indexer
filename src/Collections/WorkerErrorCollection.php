<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelIndexer\Records\WorkerErrorRecord;

final class WorkerErrorCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(WorkerErrorRecord::class);
    }
}
