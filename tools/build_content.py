#!/usr/bin/env python3
"""
Build the ready-made content pack for the admin panel.

Reads tools/content/curriculum.py + glossary.py, generates:
  server/content/curriculum.json   courses, chapters, lessons, phrases, questions, exams
  server/content/audio/*.mp3       English phrase audio + Persian teacher audio

Requirements: pip install piper-tts, ffmpeg, and Piper voices (.onnx + .onnx.json):
  en_US-lessac-medium, fa_IR-gyro-medium  (https://huggingface.co/rhasspy/piper-voices)

Usage: python3 tools/build_content.py --voices /path/to/voices
"""

import argparse
import hashlib
import json
import os
import random
import re
import subprocess
import sys
import tempfile
import wave

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)
sys.path.insert(0, os.path.join(HERE, "content"))

from curriculum import COURSES  # noqa: E402
from glossary import GLOSSARY  # noqa: E402

OUT_DIR = os.path.join(ROOT, "server", "content")
AUDIO_DIR = os.path.join(OUT_DIR, "audio")
PACK_VERSION = "1.0.0"
EN_VOICE = "en_US-lessac-medium"
FA_VOICE = "fa_IR-gyro-medium"
PUNCT = re.compile(r'[.,?!;:"…]')


class Speaker:
    def __init__(self, voices_dir):
        from piper import PiperVoice
        self.en = PiperVoice.load(os.path.join(voices_dir, EN_VOICE + ".onnx"))
        self.fa = PiperVoice.load(os.path.join(voices_dir, FA_VOICE + ".onnx"))

    def ipa(self, text):
        phonemes = " ".join("".join(s) for s in self.en.phonemize(text)).strip()
        phonemes = re.sub(r"[.,?!;:…]", "", phonemes)
        phonemes = phonemes.replace("ɹ", "r").replace("ɐ", "ə").replace("ᵻ", "ɪ")
        return "/" + re.sub(r"\s+", " ", phonemes).strip() + "/"

    def mp3(self, lang, text):
        """Synthesize text once; returns the file name inside AUDIO_DIR."""
        voice = self.en if lang == "en" else self.fa
        name = hashlib.sha1((lang + "|" + text).encode("utf-8")).hexdigest()[:20] + ".mp3"
        path = os.path.join(AUDIO_DIR, name)
        if os.path.exists(path):
            return name
        with tempfile.NamedTemporaryFile(suffix=".wav", delete=False) as tmp:
            wav_path = tmp.name
        try:
            with wave.open(wav_path, "wb") as wav:
                voice.synthesize_wav(text, wav)
            subprocess.run(
                ["ffmpeg", "-loglevel", "error", "-y", "-i", wav_path, "-ac", "1", "-ar", "22050", "-b:a", "40k", path],
                check=True,
            )
        finally:
            os.unlink(wav_path)
        return name


def words_for(sentence):
    out = {}
    for token in sentence.split():
        key = PUNCT.sub("", token)
        meaning = GLOSSARY.get(key.lower())
        if key and meaning:
            out[key] = meaning
    return out


def teacher_text(fa, tip):
    meaning = re.sub(r"[()]", "", fa).replace(" / ", " یا ")
    text = "معنی این جمله: " + meaning
    if not text.endswith((".", "!", "؟")):
        text += "."
    if tip:
        text += " " + tip
    return text


def options(rng, correct, pool, count):
    others = [p for p in dict.fromkeys(pool) if p != correct]
    picked = rng.sample(others, min(count - 1, len(others))) + [correct]
    rng.shuffle(picked)
    return picked, picked.index(correct)


def lesson_questions(rng, phrases, chapter_pool):
    """Three practice questions: meaning, reverse meaning, listening."""
    en_pool = [p["en"] for p in chapter_pool]
    fa_pool = [p["fa"] for p in chapter_pool]
    a, b, c = rng.sample(phrases, 3) if len(phrases) >= 3 else (phrases * 3)[:3]
    qs = []
    opts, ans = options(rng, a["fa"], fa_pool, 4)
    qs.append({"type": "meaning", "prompt": "معنی «" + a["en"] + "» کدام است؟", "say": "", "audio": None, "options": opts, "answer": ans})
    opts, ans = options(rng, b["en"], en_pool, 4)
    qs.append({"type": "meaning", "prompt": "«" + b["fa"] + "» به انگلیسی کدام است؟", "say": "", "audio": None, "options": opts, "answer": ans})
    opts, ans = options(rng, c["en"], en_pool, 3)
    qs.append({"type": "listen", "prompt": "گوش بده و جمله‌ای را که شنیدی انتخاب کن", "say": c["en"], "audio": c["audio"], "options": opts, "answer": ans})
    return qs


def exam_questions(rng, course_phrases, count=12):
    en_pool = [p["en"] for p in course_phrases]
    fa_pool = [p["fa"] for p in course_phrases]
    picked = rng.sample(course_phrases, min(count, len(course_phrases)))
    qs = []
    for i, p in enumerate(picked):
        kind = i % 3
        if kind == 0:
            opts, ans = options(rng, p["fa"], fa_pool, 4)
            qs.append({"type": "meaning", "prompt": "معنی «" + p["en"] + "» کدام است؟", "say": "", "audio": None, "options": opts, "answer": ans})
        elif kind == 1:
            opts, ans = options(rng, p["en"], en_pool, 4)
            qs.append({"type": "meaning", "prompt": "«" + p["fa"] + "» به انگلیسی کدام است؟", "say": "", "audio": None, "options": opts, "answer": ans})
        else:
            opts, ans = options(rng, p["en"], en_pool, 4)
            qs.append({"type": "listen", "prompt": "گوش بده و جمله‌ای را که شنیدی انتخاب کن", "say": p["en"], "audio": p["audio"], "options": opts, "answer": ans})
    return qs


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--voices", required=True, help="folder with Piper .onnx voices")
    args = parser.parse_args()

    os.makedirs(AUDIO_DIR, exist_ok=True)
    speaker = Speaker(args.voices)
    rng = random.Random(1405)
    used_audio = set()
    pack = {"version": PACK_VERSION, "voices": {"en": EN_VOICE, "fa": FA_VOICE}, "courses": []}
    total = sum(len(l["phrases"]) for c in COURSES for ch in c["chapters"] for l in ch["lessons"])
    done = 0

    for c_index, course in enumerate(COURSES):
        out_course = {k: course[k] for k in ("code", "level", "title", "title_en", "hours", "color", "description")}
        out_course["sort"] = c_index + 1
        out_course["chapters"] = []
        course_phrases = []
        for chapter in course["chapters"]:
            out_chapter = {"title": chapter["title"], "lessons": []}
            chapter_phrases = []
            for lesson in chapter["lessons"]:
                phrases = []
                for en, fa, pron, tip in lesson["phrases"]:
                    audio = speaker.mp3("en", en)
                    teacher = speaker.mp3("fa", teacher_text(fa, tip))
                    used_audio.update([audio, teacher])
                    phrases.append({
                        "en": en, "fa": fa, "ipa": speaker.ipa(en), "pron_fa": pron,
                        "words": words_for(en), "audio": audio, "teacher_audio": teacher,
                    })
                    done += 1
                    print(f"\r{done}/{total} phrases", end="", flush=True)
                chapter_phrases.extend(phrases)
                out_chapter["lessons"].append({"title": lesson["title"], "minutes": max(3, len(phrases) + 1), "phrases": phrases})
            for out_lesson in out_chapter["lessons"]:
                out_lesson["questions"] = lesson_questions(rng, out_lesson["phrases"], chapter_phrases)
            course_phrases.extend(chapter_phrases)
            out_course["chapters"].append(out_chapter)
        out_course["exam"] = exam_questions(rng, course_phrases)
        pack["courses"].append(out_course)

    # The first lesson of the first course is a free preview
    pack["courses"][0]["chapters"][0]["lessons"][0]["is_free"] = True

    for name in os.listdir(AUDIO_DIR):
        if name not in used_audio:
            os.unlink(os.path.join(AUDIO_DIR, name))

    with open(os.path.join(OUT_DIR, "curriculum.json"), "w", encoding="utf-8") as fh:
        json.dump(pack, fh, ensure_ascii=False, indent=1)
    size = sum(os.path.getsize(os.path.join(AUDIO_DIR, n)) for n in os.listdir(AUDIO_DIR))
    print(f"\n{len(pack['courses'])} courses, {total} phrases, {len(used_audio)} audio files ({size // 1024} KB)")


if __name__ == "__main__":
    main()
