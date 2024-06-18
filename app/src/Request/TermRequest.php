<?php

declare(strict_types=1);

namespace App\Request;

use App\DTO\TermDto;
use App\Exception\ValidatorException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TermRequest
{
    public function __construct(
        private readonly Request $request,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator
    ) {
    }

    public function validatePost(): array
    {
        return $this->validateTerm(Request::METHOD_POST);
    }

    public function validatePatch(): array
    {
        return $this->validateTerm(Request::METHOD_PATCH);
    }

    public function validateTerm(string $validatorGroup): array
    {
        $data = json_decode(
            json: $this->request->getContent(),
            associative: true
        );

        $term = $this->serializer->denormalize($data, TermDto::class);

        $violations = $this->validator->validate($term, groups: [$validatorGroup]);

        if (0 < count($violations)) {
            throw new ValidatorException(violations: $violations);
        }

        return $data;
    }
}