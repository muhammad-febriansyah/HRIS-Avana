<?php

namespace App\Support;

/**
 * Collects the images the assistant drew during one request.
 *
 * The chat reply is a plain text stream, so a tool result can only reach the
 * browser as prose — and asking the model to faithfully echo a long URL is a
 * coin flip. The tool drops what it made in here instead, and the controller
 * appends it to the reply once the stream ends. The picture then always
 * arrives, whatever the model chose to say about it.
 *
 * Bound `scoped` so one user's images can never surface in another's reply.
 */
final class GeneratedImageBag
{
    /** @var array<int, array{url: string, prompt: string}> */
    private array $images = [];

    public function push(string $url, string $prompt): void
    {
        $this->images[] = ['url' => $url, 'prompt' => $prompt];
    }

    /**
     * @return array<int, array{url: string, prompt: string}>
     */
    public function all(): array
    {
        return $this->images;
    }

    public function isEmpty(): bool
    {
        return $this->images === [];
    }

    /**
     * The images as the markdown the chat renders, or an empty string.
     */
    public function toMarkdown(): string
    {
        if ($this->images === []) {
            return '';
        }

        $blocks = array_map(
            static fn (array $image): string => sprintf('![%s](%s)', $image['prompt'], $image['url']),
            $this->images,
        );

        return "\n\n".implode("\n\n", $blocks);
    }
}
