# Consulting Style Chunks

This folder contains semantically split chunks of `knowledge_examples/personal/consulting_style.txt`.

Import example:

```bash
for f in /app/knowledge_examples/personal/consulting_style_chunks/*.txt; do
  php bin/console knowledge:import "$f" 858361483
done
```

`scripts/reload_knowledge_base.sh` already imports these chunk files as canonical knowledge.
