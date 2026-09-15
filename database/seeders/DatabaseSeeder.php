<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Like;
use App\Models\Link;
use App\Models\PollOption;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application with sample data for local development.
     */
    public function run(): void
    {
        $demo = User::factory()->create([
            'name' => 'Demo User',
            'username' => 'demo',
            'email' => 'demo@example.com',
            'bio' => 'This is the account you should log in with. Password: password',
        ]);

        $people = collect([
            ['name' => 'Ada Lovelace', 'username' => 'ada', 'bio' => 'Analytical engines and poetry.'],
            ['name' => 'Grace Hopper', 'username' => 'grace', 'bio' => 'Compilers, nanoseconds, and the occasional bug.'],
            ['name' => 'Linus Torvalds', 'username' => 'linus', 'bio' => 'Just for fun.'],
            ['name' => 'Margaret Hamilton', 'username' => 'margaret', 'bio' => 'Software engineering, before it had a name.'],
            ['name' => 'Taylor Otwell', 'username' => 'taylor', 'bio' => 'Building Laravel.'],
            ['name' => 'Caleb Porzio', 'username' => 'caleb', 'bio' => 'Livewire and Alpine.'],
            ['name' => 'Sara Chen', 'username' => 'sara', 'bio' => 'Product designer. Big fan of whitespace.'],
        ])->map(fn (array $attributes): User => User::factory()->create([
            ...$attributes,
            'email' => $attributes['username'].'@example.com',
        ]));

        $everyone = $people->push($demo);

        // Links for the demo user, with a custom (non-chronological) order.
        $demoLinks = collect([
            ['description' => 'GitHub', 'url' => 'https://github.com', 'click_count' => 42],
            ['description' => 'Personal blog', 'url' => 'https://example.com/blog', 'click_count' => 128],
            ['description' => 'LinkedIn', 'url' => 'https://linkedin.com', 'click_count' => 7],
            ['description' => 'Newsletter', 'url' => 'https://example.com/newsletter', 'click_count' => 63],
            ['description' => 'Twitter / X', 'url' => 'https://x.com', 'click_count' => 19],
        ])->map(fn (array $attributes): Link => Link::factory()->create([...$attributes, 'user_id' => $demo->id]));

        $demo->update([
            'links_sort' => [
                $demoLinks[1]->id,
                $demoLinks[3]->id,
                $demoLinks[0]->id,
                $demoLinks[4]->id,
                $demoLinks[2]->id,
            ],
        ]);

        // Links for everyone else.
        $people->each(function (User $user): void {
            Link::factory()->create(['user_id' => $user->id, 'description' => 'Website', 'url' => "https://{$user->username}.example.com", 'click_count' => random_int(0, 200)]);
            Link::factory()->create(['user_id' => $user->id, 'description' => 'GitHub', 'url' => "https://github.com/{$user->username}", 'click_count' => random_int(0, 200)]);
            Link::factory()->create(['user_id' => $user->id, 'description' => 'Mastodon', 'url' => "https://mastodon.social/@{$user->username}", 'click_count' => random_int(0, 200)]);
        });

        // Follow graph.
        $everyone->each(function (User $user) use ($everyone): void {
            $everyone->where('id', '!=', $user->id)
                ->random(random_int(2, 5))
                ->each(fn (User $target) => $user->following()->attach($target->id));
        });

        // Answered questions, with hashtags so the explore pages have content.
        $topics = ['#laravel', '#php', '#design', '#tailwind', '#livewire', '#testing', '#opensource'];

        $questions = collect(range(1, 40))->map(function (int $i) use ($everyone, $topics): Question {
            $to = $everyone->random();
            $from = $everyone->where('id', '!=', $to->id)->random();
            $topic = $topics[$i % count($topics)];

            return Question::factory()->create([
                'from_id' => $from->id,
                'to_id' => $to->id,
                'content' => fake()->sentence(random_int(6, 14)).' '.$topic,
                'anonymously' => $i % 3 === 0,
                'answer' => fake()->paragraph(random_int(1, 3)),
                'answer_created_at' => now()->subHours(random_int(1, 24 * 6)),
                'created_at' => now()->subHours(random_int(24 * 6, 24 * 10)),
                'views' => random_int(5, 900),
            ]);
        });

        // Likes.
        $questions->each(function (Question $question) use ($everyone): void {
            $everyone->random(random_int(0, 6))
                ->each(fn (User $user) => Like::factory()->create(['user_id' => $user->id, 'question_id' => $question->id]));
        });

        // Bookmarks for the demo user.
        $questions->random(5)->each(fn (Question $question) => $demo->bookmarks()->create(['question_id' => $question->id]));

        // A live poll.
        $poll = Question::factory()->poll()->create([
            'from_id' => $demo->id,
            'to_id' => $demo->id,
            'content' => 'Which do you reach for first on a new project? #laravel',
            'answer' => '',
            'answer_created_at' => null,
            'anonymously' => false,
            'created_at' => now()->subHours(3),
        ]);

        foreach (['Livewire', 'Inertia + Vue', 'Inertia + React', 'Plain Blade'] as $text) {
            PollOption::factory()->create(['question_id' => $poll->id, 'text' => $text]);
        }
    }
}
