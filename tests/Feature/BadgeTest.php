<?php

declare(strict_types=1);

namespace Ahmdrv\Navigation\Tests\Feature;

use Ahmdrv\Navigation\Facades\Navigation;
use Ahmdrv\Navigation\Tests\Fixtures\User;
use Ahmdrv\Navigation\Tests\TestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

final class BadgeTest extends TestCase
{
    /** @return iterable<string, array{mixed}> */
    public static function emptyBadges(): iterable
    {
        foreach ([null, false, '', 0, 0.0, '0'] as $index => $value) {
            yield (string) $index => [$value];
        }
    }

    #[DataProvider('emptyBadges')]
    public function test_empty_labels_skip_badge_and_color_evaluation(mixed $label): void
    {
        Navigation::register([Navigation::item('Empty')->badge($label, static function (): string {
            self::fail('Color must not run for an empty badge.');
        })]);
        self::assertNull(Navigation::build()[0]->badge);
    }

    public function test_static_badges_preserve_label_and_color_metadata(): void
    {
        Navigation::register([
            Navigation::item('Token')->badge(12, 'danger'),
            Navigation::item('Hex')->badge('new', '#ff5722'),
            Navigation::item('Variable')->badge(1.5, 'var(--brand-color)'),
        ]);
        $tree = Navigation::build();
        self::assertSame(['label' => 12, 'color' => 'danger'], $tree[0]->badge->toArray());
        self::assertSame('#ff5722', $tree[1]->badge->color);
        self::assertSame('var(--brand-color)', $tree[2]->badge->color);
    }

    public function test_callbacks_run_once_per_build_with_documented_arguments_and_no_user_cache(): void
    {
        $labels = $colors = 0;
        $item = Navigation::item('Unread')->badge(
            function ($user, $item, $context) use (&$labels): int {
                $labels++;
                self::assertSame('Unread', $item->getLabel());
                self::assertSame('home', $context->routeName());
                return $user->getAuthIdentifier();
            },
            function ($label, $user, $item, $context, $optional = 'default') use (&$colors): string {
                $colors++;
                self::assertSame($user->getAuthIdentifier(), $label);
                self::assertSame('Unread', $item->getLabel());
                self::assertSame('home', $context->routeName());
                self::assertSame('default', $optional);
                return 'danger';
            },
        );
        Navigation::register([$item]);
        self::assertSame(1, Navigation::build($this->context(new User(1), 'home'))[0]->badge->label);
        self::assertSame(2, Navigation::build($this->context(new User(2), 'home'))[0]->badge->label);
        self::assertSame(2, $labels);
        self::assertSame(2, $colors);
        self::assertInstanceOf(\Closure::class, $item->getBadgeLabel());
    }

    public function test_hidden_items_never_evaluate_badges(): void
    {
        Navigation::register([Navigation::item('Hidden')->hidden()->badge(static function (): int {
            self::fail('Hidden badge must not run.');
        })]);
        self::assertSame([], Navigation::build());
    }

    public function test_callback_empty_badge_label_skips_color(): void
    {
        Navigation::register([Navigation::item('Empty dynamic')->badge(static fn () => '0', static function (): string {
            self::fail('Empty dynamic badge must skip its color.');
        })]);
        self::assertNull(Navigation::build()[0]->badge);
    }

    public function test_invalid_color_result_fails_clearly(): void
    {
        Navigation::register([Navigation::item('Invalid color')->badge(1, static fn () => null)]);
        $this->expectException(InvalidArgumentException::class);
        Navigation::build();
    }

    public function test_invalid_badge_result_fails_clearly(): void
    {
        Navigation::register([Navigation::item('Invalid')->badge(static fn () => new \stdClass())]);
        $this->expectException(InvalidArgumentException::class);
        Navigation::build();
    }
}
