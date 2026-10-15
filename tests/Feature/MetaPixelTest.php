<?php

test('sends the AddToCart event from the Meta Pixel code in the page head', function (): void {
    $this->withoutVite();

    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder([
            "fbq('track', 'PageView');",
            "fbq('track', 'AddToCart');",
            "fbq('track', 'Lead');",
        ], false);
});
