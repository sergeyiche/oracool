#!/usr/bin/env python3
"""
Скрипт подготовки диалога для импорта в базу знаний

Использование:
    python3 prepare_dialogue.py input.txt output_directory
"""

import sys
import re
import os

def split_dialogue(input_file, output_dir, max_chars=1500):
    """Разбивает диалог на отдельные файлы с контролем длины"""
    
    with open(input_file, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Разбиваем на блоки ВОПРОС/ОТВЕТ
    blocks = []
    current_block = {"question": "", "answer": ""}
    in_answer = False
    
    for line in content.split('\n'):
        line_stripped = line.strip()
        
        if line_stripped.startswith('ВОПРОС:'):
            # Сохраняем предыдущий блок
            if current_block["question"] and current_block["answer"]:
                blocks.append(current_block)
            
            # Начинаем новый блок
            current_block = {"question": line_stripped[7:].strip(), "answer": ""}
            in_answer = False
        elif line_stripped.startswith('ОТВЕТ:'):
            current_block["answer"] = line_stripped[6:].strip()
            in_answer = True
        elif line_stripped and in_answer:
            # Продолжаем ответ
            if current_block["answer"]:
                current_block["answer"] += " " + line_stripped
            else:
                current_block["answer"] = line_stripped
        elif line_stripped and not in_answer:
            # Продолжаем вопрос
            if current_block["question"]:
                current_block["question"] += " " + line_stripped
            else:
                current_block["question"] = line_stripped
    
    # Сохраняем последний блок
    if current_block["question"] and current_block["answer"]:
        blocks.append(current_block)
    
    print(f"📊 Найдено блоков Q&A: {len(blocks)}")
    
    # Разбиваем длинные ответы
    final_blocks = []
    split_count = 0
    
    for i, block in enumerate(blocks):
        q = block["question"]
        a = block["answer"]
        
        if len(a) <= max_chars:
            # Короткий ответ - оставляем как есть
            final_blocks.append({"question": q, "answer": a})
        else:
            # Длинный ответ - разбиваем на части
            split_count += 1
            sentences = re.split(r'(?<=[.!?])\s+', a)
            
            chunk = ""
            part_num = 1
            
            for sentence in sentences:
                if len(chunk) + len(sentence) <= max_chars:
                    chunk += sentence + " "
                else:
                    # Сохраняем текущий чанк
                    if chunk:
                        final_blocks.append({
                            "question": f"{q} (часть {part_num})",
                            "answer": chunk.strip()
                        })
                        part_num += 1
                    chunk = sentence + " "
            
            # Сохраняем последний чанк
            if chunk:
                final_blocks.append({
                    "question": f"{q} (часть {part_num})" if part_num > 1 else q,
                    "answer": chunk.strip()
                })
    
    print(f"📏 Разбито длинных ответов: {split_count}")
    print(f"📝 Итого файлов будет создано: {len(final_blocks)}")
    print()
    
    # Создаём выходную директорию
    os.makedirs(output_dir, exist_ok=True)
    
    # Сохраняем в отдельные файлы
    for i, block in enumerate(final_blocks, 1):
        filename = f"{output_dir}/{i:03d}_qa.txt"
        
        with open(filename, 'w', encoding='utf-8') as f:
            # Объединяем в ОДИН блок (один перенос между вопросом и ответом)
            f.write(f"ВОПРОС: {block['question']}\n")
            f.write(f"ОТВЕТ: {block['answer']}\n")
        
        if i % 10 == 0:
            print(f"  ✓ Создано {i}/{len(final_blocks)} файлов...")
    
    print(f"\n✅ Создано {len(final_blocks)} файлов в директории: {output_dir}")
    return len(final_blocks)

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("Использование: python3 prepare_dialogue.py input.txt output_directory")
        sys.exit(1)
    
    input_file = sys.argv[1]
    output_dir = sys.argv[2]
    
    if not os.path.exists(input_file):
        print(f"❌ Файл не найден: {input_file}")
        sys.exit(1)
    
    print("╔═══════════════════════════════════════════════════════════════╗")
    print("║     📝 ПОДГОТОВКА ДИАЛОГА ДЛЯ ИМПОРТА                        ║")
    print("╚═══════════════════════════════════════════════════════════════╝")
    print()
    print(f"📥 Входной файл: {input_file}")
    print(f"📂 Выходная директория: {output_dir}")
    print()
    print("🔄 Обработка...")
    print()
    
    count = split_dialogue(input_file, output_dir)
    
    print()
    print("💡 Теперь импортируйте:")
    print(f"   cd /www/oracool/knowledge_examples/personal")
    print(f"   ./import_dialogue.sh 858361483 {os.path.basename(output_dir)}")
    print()
