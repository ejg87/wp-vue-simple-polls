<?php
/**
 * Plugin Name: Simple Poll
 * Description: A simple poll plugin with REST API endpoints for fetching polls and submitting votes.
 * Version: .01
 * Author: EJ Goralewski
 */

defined('ABSPATH') or die('No script kiddies please!');

function simple_poll_register_post_type() {
    register_post_type('simple_poll', [
        'labels' => [
            'name' => 'Polls',
            'singular_name' => 'Poll',
        ],
        'public' => true,
        'has_archive' => true,
        'show_in_rest' => true,
        'supports' => ['title', 'editor'],
    ]);
}
add_action('init', 'simple_poll_register_post_type');

function simple_poll_register_rest_route() {
    register_rest_route('simple-poll/v1', '/polls', [
        'methods' => 'GET',
        'callback' => 'simple_poll_get_polls',
    ]);
}
add_action('rest_api_init', 'simple_poll_register_rest_route');

function simple_poll_get_polls() {
    $polls_query = new WP_Query([
        'post_type' => 'simple_poll',
        'posts_per_page' => -1,
    ]);

    $polls = [];

    while ($polls_query->have_posts()) {
        $polls_query->the_post();
        $id = get_the_ID();
        $votes = get_post_meta($id, 'simple_poll_votes', true);
        if (!is_array($votes)) {
            $votes = [];
        }

        $content = get_the_content();
        $cleanedContent = preg_replace('/<!--(.|\s)*?-->/', '', $content);

        $answers = explode("\n", $cleanedContent);
        $answers = array_filter($answers, function($answer) {
            return !empty(trim($answer));
        });

        $answers_with_votes = array_map(function($answer) use ($votes) {
            return ['answer' => trim($answer), 'votes' => $votes[$answer] ?? 0];
        }, $answers);

        $polls[] = [
            'id' => $id,
            'title' => get_the_title(),
            'answers' => array_values($answers_with_votes),
        ];
    }

    wp_reset_postdata();

    return new WP_REST_Response($polls, 200);
}

function simple_poll_submit_vote($request) {
    $poll_id = $request['id'];
    $answer = $request->get_param('answer');

    $votes = get_post_meta($poll_id, 'simple_poll_votes', true);
    if (!is_array($votes)) {
        $votes = [];
    }

    if (!array_key_exists($answer, $votes)) {
        $votes[$answer] = 1;
    } else {
        $votes[$answer]++;
    }

    update_post_meta($poll_id, 'simple_poll_votes', $votes);

    return new WP_REST_Response(['success' => true, 'votes' => $votes], 200);
}

function simple_poll_register_vote_route() {
    register_rest_route('simple-poll/v1', '/submit-vote/(?P<id>\d+)', [
        'methods' => 'POST',
        'callback' => 'simple_poll_submit_vote',
        'args' => [
            'id' => [
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }
            ],
            'answer' => [
                'required' => true,
                'validate_callback' => function($param, $request, $key) {
                    return is_string($param);
                }
            ],
        ],
        'permission_callback' => '__return_true',
    ]);
}
add_action('rest_api_init', 'simple_poll_register_vote_route');
