<?php

namespace App\Helpers;

class NameParser
{
    /**
     * Parse full name using right-to-left logic with suffix detection
     * 
     * Logic:
     * 1. Start from rightmost word → Check if Suffix (Jr., Sr., II, III, etc.)
     * 2. Next word → Last Name
     * 3. Check left word → If compound prefix (dela, san, etc.) → Add to Last Name
     * 4. Next word to left → Middle Name
     * 5. Everything remaining → First Name
     */
    public static function parse($fullName)
    {
        if (empty($fullName)) {
            return [
                'first_name' => '', 
                'middle_name' => '', 
                'middle_initial' => '',
                'last_name' => '', 
                'suffix' => ''
            ];
        }
        
        $names = preg_split('/\s+/', trim($fullName));
        $count = count($names);
        
        if ($count == 1) {
            return [
                'first_name' => $names[0], 
                'middle_name' => '', 
                'middle_initial' => '',
                'last_name' => '', 
                'suffix' => ''
            ];
        }
        
        // Parse right-to-left
        $suffix = '';
        $lastNameParts = [];
        $i = $count - 1;
        
        // Step 1: Check if rightmost word is a suffix
        if (self::isSuffix($names[$i])) {
            $suffix = $names[$i];
            $i--;
            
            // If only suffix remains, return it
            if ($i < 0) {
                return [
                    'first_name' => '', 
                    'middle_name' => '', 
                    'middle_initial' => '',
                    'last_name' => '', 
                    'suffix' => $suffix
                ];
            }
        }
        
        // Step 2: Rightmost word (after suffix) is last name
        $lastNameParts[] = $names[$i];
        $i--;
        
        // Step 3: Check if previous words are compound prefixes
        while ($i >= 0 && self::isCompoundPrefix($names[$i])) {
            array_unshift($lastNameParts, $names[$i]);
            $i--;
        }
        
        // Step 4: Next word is middle name
        $middleName = '';
        $middleInitial = '';
        if ($i >= 0) {
            $middleName = $names[$i];
            // Extract middle initial (first letter)
            $middleInitial = strtoupper(substr($middleName, 0, 1));
            $i--;
        }
        
        // Step 5: Everything remaining is first name
        $firstNameParts = array_slice($names, 0, $i + 1);
        $firstName = implode(' ', $firstNameParts);
        
        return [
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'middle_initial' => $middleInitial,
            'last_name' => implode(' ', $lastNameParts),
            'suffix' => $suffix,
        ];
    }
    
    /**
     * Convert a name string to proper title case,
     * keeping compound prefixes (de, dela, delos, etc.) in lowercase.
     *
     * e.g. "JUAN DELA CRUZ" → "Juan dela Cruz"
     *      "MARIA DE LOS SANTOS" → "Maria de los Santos"
     */
    public static function toTitleCase(string $name): string
    {
        if (empty(trim($name))) {
            return $name;
        }

        $words = preg_split('/\s+/', trim($name));
        $result = [];

        foreach ($words as $index => $word) {
            if ($index > 0 && self::isCompoundPrefix($word)) {
                // Keep compound prefixes lowercase (e.g. de, dela, delos, san)
                $result[] = strtolower($word);
            } else {
                // Capitalize first letter, lowercase the rest
                $result[] = mb_strtoupper(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8')
                           . mb_strtolower(mb_substr($word, 1, null, 'UTF-8'), 'UTF-8');
            }
        }

        return implode(' ', $result);
    }

    private static function isSuffix($word)
    {
        $suffixes = [
            'jr', 'jr.', 'junior',
            'sr', 'sr.', 'senior',
            'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x',
            '2nd', '3rd', '4th', '5th',
            'i', // Roman numeral I
        ];
        
        return in_array(strtolower(str_replace('.', '', $word)), $suffixes);
    }
    
    private static function isCompoundPrefix($word)
    {
        $compoundPrefixes = [
            'dela', 'de', 'del',
            'san', 'santa', 'santo',
            'delos', 'las', 'los',
            'van', 'von', 'di', 'da'
        ];
        
        return in_array(strtolower($word), $compoundPrefixes);
    }
}
