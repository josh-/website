<?php

namespace App\Console\Commands;

use App\Event;
use App\Post;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Parser;

class LoadPosts extends Command
{
    protected $signature = 'cocoa:posts';

    protected $description = 'Load posts from posts.yml.';

    public function handle()
    {
        $yaml = new Parser();

        $posts = $yaml->parse(file_get_contents(base_path('posts.yml')));

        foreach ($posts['posts'] as $post) {
            $slug = Str::slug($post['title']);

            $newPost = Post::firstOrNew(['slug' => $slug]);
            $newPost->timestamps = false;

            $newPost->title = $post['title'];
            $newPost->subtitle = $post['subtitle'];
            $newPost->body = $post['body'];
            $newPost->frontpage = $post['frontpage'];
            if (isset($post['coverImage'])) {
                $newPost->coverImage = $post['coverImage'];
            }

            $date = Carbon::createFromTimestamp($post['created_at']);

            $newPost->created_at = $date;
            $newPost->updated_at = Carbon::now();

            $newPost->save();

            $newPost->events()->detach();

            if (isset($post['events']) && is_array($post['events'])) {
                $intEvents = array_filter(array_map(function ($id) {
                    if (is_numeric($id)) {
                        return (int) $id;
                    }
                    return null;
                }, $post['events']), function ($id) {
                    return !is_null($id);
                });

                if (!empty($intEvents)) {
                    $events = (new Event)->whereIn('id', $intEvents)->get();
                } else {
                    $events = (new Event)->whereIn('slug', $post['events'])->get();
                }

                if (!$events->isEmpty()) {
                    $newPost->events()->saveMany($events);
                }
            }

            $this->info("Loaded: $newPost->title");
        }
    }
}
