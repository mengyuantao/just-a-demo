CREATE TABLE card (uuid text primary key, user_id int, name text, caption text, site text, page_url text, image_url text, video_url text, insert_time int key, status int, is_deleted int);
CREATE TABLE card_count(card_id text primary key, like_count int, unlike_count int, show_count, click_count) without rowid;
CREATE TABLE user (name text uniqe key, email text primary key, password text, magic text, insert_time int, update_time int, status int, is_deleted int);
