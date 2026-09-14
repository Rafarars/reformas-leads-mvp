<?php

use Reformas\Leads\Lead\Domain\InvalidPhoneNumberError;
use Reformas\Shared\Domain\ConflictError;
use Reformas\Shared\Domain\DomainError;
use Reformas\Shared\Domain\InvalidArgumentError;
use Reformas\Shared\Domain\NotFoundError;
use Reformas\Shared\Domain\UnauthorizedError;

const ERROR_CATEGORIES = [InvalidArgumentError::class, NotFoundError::class, ConflictError::class, UnauthorizedError::class];

/**
 * @return list<class-string<DomainError>>
 */
function domainErrorClasses(): array
{
    $root = dirname(__DIR__, 4).'/src';
    $classes = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        $path = $file->getPathname();

        if (! str_contains($path, '/Domain/') || ! str_ends_with($path, 'Error.php')) {
            continue;
        }

        $class = 'Reformas\\'.str_replace('/', '\\', substr($path, strlen($root) + 1, -4));

        if (! (new ReflectionClass($class))->isAbstract()) {
            $classes[] = $class;
        }
    }

    sort($classes);

    return $classes;
}

// El analizador también se prueba: con una ruta mal escrita no encontraría nada y todo pasaría.
it('finds the domain errors it has to check', function () {
    expect(domainErrorClasses())->toContain(InvalidPhoneNumberError::class);
});

// Un error que herede directamente de DomainError saldría como 500 sin que nadie lo note.
it('makes every domain error belong to exactly one category', function (string $class) {
    $categories = array_filter(ERROR_CATEGORIES, static fn (string $category): bool => is_subclass_of($class, $category));

    expect($categories)->toHaveCount(1);
})->with(fn () => domainErrorClasses());

it('gives every domain error a readable type and a public message without internal data', function (string $class) {
    $error = (new ReflectionClass($class))->newInstanceWithoutConstructor();

    expect($error->errorType())->toMatch('/^[a-z]+(_[a-z]+)*$/')
        ->and($error->publicMessage())->not->toBeEmpty()->not->toContain('<');
})->with(fn () => domainErrorClasses());
