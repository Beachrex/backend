#!/bin/bash

echo "Checking for variable naming issues in controllers..."
echo

for file in app/Http/Controllers/Api/V1/*Controller.php; do
    controller=$(basename "$file")
    
    # Check for undefined variable usage in Resource::collection()
    var_defined=$(grep -o '\$[a-zA-Z_]*\s*=\s*$this->cacheRemember' "$file" | sed 's/\s*=.*//' | head -1)
    var_used=$(grep -o 'Resource::collection(\$[a-zA-Z_]*)' "$file" | sed 's/.*(\$//' | sed 's/).*//' | head -1)
    
    if [ -n "$var_defined" ] && [ -n "$var_used" ]; then
        var_defined_clean=$(echo "$var_defined" | tr -d '$')
        if [ "$var_defined" != "\$$var_used" ]; then
            echo "❌ $controller"
            echo "   Defined: $var_defined"
            echo "   Used: \$$var_used"
            echo
        fi
    fi
done

