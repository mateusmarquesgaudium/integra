<?php

namespace src\geral;

use src\Helpers\Webhook;

class WebhookDiscord {
        private string $url;
        private string $content;
        private string $username;
        private string $avatarUrl;

        public function __construct(string $url, string $username, string $content, string $avatarUrl)
        {
            $this->url = $url;
            $this->content = $content;
            $this->username = $username;
            $this->avatarUrl = $avatarUrl;
        }

        public function send(): void
        {
            $data = [
                'content' => $this->content,
                'username' => $this->username,
                'avatar_url' => $this->avatarUrl,
                'embeds' => null,
            ];

            $webhook = new Webhook($this->url, json_encode($data), ['Content-Type: application/json']);
            $webhook->sendMessage();
        }
}