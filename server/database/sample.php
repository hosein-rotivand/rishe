<?php
/**
 * Sample content: one published A1 course so the app has something to show right after install.
 * Audio files are not included; the app falls back to the phone's text-to-speech.
 */

declare(strict_types=1);

return static function (): void {
	$now       = rishe_now();
	$course_id = Rishe_DB::insert('rishe_courses', [
		'title'       => 'انگلیسی از پایه',
		'title_en'    => 'English Foundations',
		'code'        => 'A1',
		'level'       => 'A1',
		'description' => 'از سلام و معرفی شروع می‌کنید و تا مکالمه‌های ساده‌ی روزمره پیش می‌روید. هر مرحله شامل صدای عبارت، تلفظ و معنی فارسی است.',
		'hours'       => 26,
		'color'       => 'brand',
		'is_premium'  => 0,
		'price'       => 0,
		'pass_score'  => 70,
		'sort'        => 1,
		'status'      => 'published',
		'created_at'  => $now,
	]);

	$chapters = [
		'سلام و معرفی' => [
			'سلام کردن' => [
				'phrases'   => [
					['Hello!', 'سلام!', '/həˈloʊ/', 'هِـلو', ['Hello' => 'سلام']],
					['Good morning.', 'صبح بخیر.', '/ɡʊd ˈmɔːrnɪŋ/', 'گود مورنینگ', ['Good' => 'خوب', 'morning' => 'صبح']],
					['How are you?', 'حالت چطور است؟', '/haʊ ɑːr juː/', 'هاو آر یو', ['How' => 'چطور', 'are' => 'هستی', 'you' => 'تو، شما']],
					["I'm fine, thanks.", 'خوبم، ممنون.', '/aɪm faɪn θæŋks/', 'آیم فاین، تنکس', ["I'm" => 'من هستم', 'fine' => 'خوب', 'thanks' => 'ممنون']],
				],
				'questions' => [
					['meaning', 'معنی «Good morning» کدام است؟', '', ['صبح بخیر', 'شب بخیر', 'خداحافظ', 'ممنون'], 0],
					['listen', 'گوش بده و جمله‌ای را که شنیدی انتخاب کن', 'How are you?', ['Who are you?', 'How are you?', 'Where are you?'], 1],
				],
			],
			'معرفی خود' => [
				'phrases'   => [
					['Hello, my name is Sara.', 'سلام، اسم من ساراست.', '/həˈloʊ, maɪ neɪm ɪz ˈsɛrə/', 'هِـلو، مای نِیم ایز سِرا', ['Hello' => 'سلام', 'my' => 'مالِ من', 'name' => 'اسم', 'is' => 'است', 'Sara' => 'سارا (اسم خاص)']],
					['Nice to meet you.', 'از آشنایی با شما خوشوقتم.', '/naɪs tə miːt juː/', 'نایس تو میت یو', ['Nice' => 'خوب، خوشایند', 'to' => 'به، برای', 'meet' => 'ملاقات کردن', 'you' => 'تو، شما']],
					['Where are you from?', 'اهل کجا هستی؟', '/wɛr ɑːr juː frʌm/', 'وِر آر یو فِرام', ['Where' => 'کجا', 'are' => 'هستی', 'you' => 'تو، شما', 'from' => 'از']],
					["I'm from Iran.", 'من اهل ایران هستم.', '/aɪm frʌm ɪˈrɑːn/', 'آیم فِرام ایران', ["I'm" => 'من هستم', 'from' => 'از', 'Iran' => 'ایران']],
				],
				'questions' => [
					['meaning', 'معنی «Nice to meet you» کدام است؟', '', ['از آشنایی با شما خوشوقتم.', 'حالت چطور است؟', 'خداحافظ، فردا می‌بینمت.', 'اسم شما چیست؟'], 0],
					['listen', 'گوش بده و جمله‌ای را که شنیدی انتخاب کن', 'Where are you from?', ['Where do you live?', 'Where are you from?', 'What is your name?'], 1],
				],
			],
		],
		'اعداد و زمان' => [
			'اعداد ۱ تا ۵' => [
				'phrases'   => [
					['one, two, three', 'یک، دو، سه', '/wʌn tuː θriː/', 'وان، تو، تری', ['one' => 'یک', 'two' => 'دو', 'three' => 'سه']],
					['four, five', 'چهار، پنج', '/fɔːr faɪv/', 'فور، فایو', ['four' => 'چهار', 'five' => 'پنج']],
				],
				'questions' => [
					['meaning', '«three» یعنی چند؟', '', ['۲', '۳', '۴', '۵'], 1],
				],
			],
		],
	];

	$ch_sort = 0;
	foreach ($chapters as $chapter_title => $lessons) {
		$chapter_id = Rishe_DB::insert('rishe_chapters', ['course_id' => $course_id, 'title' => $chapter_title, 'sort' => ++$ch_sort]);
		$l_sort     = 0;
		foreach ($lessons as $lesson_title => $content) {
			$lesson_id = Rishe_DB::insert('rishe_lessons', [
				'chapter_id' => $chapter_id,
				'title'      => $lesson_title,
				'minutes'    => 5,
				'is_free'    => $l_sort === 0 && $ch_sort === 1 ? 1 : 0,
				'sort'       => ++$l_sort,
				'status'     => 'published',
			]);
			foreach ($content['phrases'] as $i => [$en, $fa, $ipa, $pron, $words]) {
				Rishe_DB::insert('rishe_phrases', [
					'lesson_id'  => $lesson_id,
					'en'         => $en,
					'fa'         => $fa,
					'ipa'        => $ipa,
					'pron_fa'    => $pron,
					'words_json' => json_encode($words, JSON_UNESCAPED_UNICODE),
					'sort'       => $i + 1,
				]);
			}
			foreach ($content['questions'] as $i => [$type, $prompt, $say, $options, $answer]) {
				Rishe_DB::insert('rishe_questions', [
					'owner_type'   => 'lesson',
					'owner_id'     => $lesson_id,
					'type'         => $type,
					'prompt'       => $prompt,
					'say_text'     => $say,
					'options_json' => json_encode($options, JSON_UNESCAPED_UNICODE),
					'answer'       => $answer,
					'sort'         => $i + 1,
				]);
			}
		}
	}

	$exam = [
		['meaning', 'معنی «How are you?» کدام است؟', '', ['حالت چطور است؟', 'کجا هستی؟', 'اسمت چیست؟', 'اهل کجایی؟'], 0],
		['meaning', 'جواب مناسب برای «Nice to meet you» چیست؟', '', ['Good night.', 'Nice to meet you too.', "I'm from Iran.", 'Thank you, bye.'], 1],
		['listen', 'گوش بده و انتخاب کن', 'Good morning.', ['Good evening.', 'Good night.', 'Good morning.'], 2],
		['meaning', '«five» یعنی چند؟', '', ['۳', '۴', '۵', '۶'], 2],
	];
	foreach ($exam as $i => [$type, $prompt, $say, $options, $answer]) {
		Rishe_DB::insert('rishe_questions', [
			'owner_type'   => 'exam',
			'owner_id'     => $course_id,
			'type'         => $type,
			'prompt'       => $prompt,
			'say_text'     => $say,
			'options_json' => json_encode($options, JSON_UNESCAPED_UNICODE),
			'answer'       => $answer,
			'sort'         => $i + 1,
		]);
	}

	Rishe_DB::insert('rishe_plans', ['title' => 'اشتراک یک‌ماهه', 'days' => 30, 'price' => 99000, 'status' => 'active', 'sort' => 1]);
	Rishe_DB::insert('rishe_plans', ['title' => 'اشتراک سه‌ماهه', 'days' => 90, 'price' => 249000, 'status' => 'active', 'sort' => 2]);
};
