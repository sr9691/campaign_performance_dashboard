<?php
/**
 * Industry Taxonomy Configuration
 * 
 * RB2B-compatible industry taxonomy for scoring rules
 * Format: 'Top-Level Category|Sub-Category'
 * 
 * @package DirectReach
 * @subpackage RTR_Scoring_System
 * @since 2.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get complete industry taxonomy
 * 
 * @return array Hierarchical array of industries
 */
function rtr_get_industry_taxonomy() {
    return [
        'Agriculture' => [
            'Agriculture',
            'Dairy',
            'Farming',
            'Fishery',
            'Ranching'
        ],
        'Automotive' => [],
        'Construction' => [
            'Construction',
            'Architecture & Planning',
            'Civil Engineering'
        ],
        'Creative Arts and Entertainment' => [
            'Creative Arts and Entertainment',
            'Animation',
            'Computer Games',
            'Design',
            'Entertainment',
            'Fine Art',
            'Gambling & Casinos',
            'Graphic Design',
            'Motion Pictures & Film',
            'Music',
            'Performing Arts',
            'Photography'
        ],
        'Education' => [
            'Education',
            'Education Management',
            'E-Learning',
            'Higher Education',
            'Primary/Secondary Education'
        ],
        'Energy' => [
            'Energy',
            'Oil & Energy',
            'Renewables & Environment'
        ],
        'Finance and Banking' => [
            'Finance and Banking',
            'Accounting',
            'Banking',
            'Capital Markets',
            'Financial Services',
            'Insurance',
            'Investment Banking',
            'Investment Management',
            'Venture Capital & Private Equity'
        ],
        'Food and Beverage' => [
            'Food & Beverages',
            'Food Production',
            'Restaurants',
            'Wine & Spirits'
        ],
        'Government and Public Administration' => [
            'Government and Public Administration',
            'Defense & Space',
            'Government Administration',
            'Government Relations',
            'International Affairs',
            'Judiciary',
            'Law Enforcement',
            'Legislative Office',
            'Military',
            'Political Organization',
            'Public Policy',
            'Public Safety'
        ],
        'Health and Pharmaceuticals' => [
            'Health and Pharmaceuticals',
            'Alternative Medicine',
            'Biotechnology',
            'Health, Wellness & Fitness',
            'Health, Wellness and Fitness',
            'Hospital & Health Care',
            'Medical Devices',
            'Medical Practice',
            'Mental Health Care',
            'Pharmaceuticals',
            'Veterinary'
        ],
        'Information Technology' => [
            'Information Technology',
            'Computer & Network Security',
            'Computer Hardware',
            'Computer Networking',
            'Computer Software',
            'Information Services',
            'Information Technology & Services',
            'Information Technology and Services'
        ],
        'Manufacturing' => [
            'Apparel & Fashion',
            'Building Materials',
            'Business Supplies & Equipment',
            'Chemicals',
            'Consumer Electronics',
            'Cosmetics',
            'Electrical & Electronic Manufacturing',
            'Furniture',
            'Glass, Ceramics & Concrete',
            'Industrial Automation',
            'Luxury Goods & Jewelry',
            'Machinery',
            'Manufacturing',
            'Mining & Metals',
            'Nanotechnology',
            'Packaging & Containers',
            'Paper & Forest Products',
            'Printing',
            'Railroad Manufacture',
            'Semiconductors',
            'Sporting Goods',
            'Textiles',
            'Tobacco'
        ],
        'Marketing & Advertising' => [
            'Marketing & Advertising',
            'Marketing and Advertising',
            'Public Relations & Communications',
            'Public Relations and Communications'
        ],
        'Media and Publishing' => [
            'Media and Publishing',
            'Broadcast Media',
            'Media Production',
            'Newspapers',
            'Online Media',
            'Writing & Editing'
        ],
        'Non-Profit and Social Services' => [
            'Non-Profit and Social Services',
            'Civic & Social Organization',
            'Fund-Raising',
            'Fundraising',
            'Individual & Family Services',
            'Libraries',
            'Museums & Institutions',
            'Museums and Institutions',
            'Non Profit Organization Management',
            'Non-Profit Organization Management',
            'Nonprofit Organization Management',
            'Philanthropy',
            'Religious Institutions'
        ],
        'Professional and Business Services' => [
            'Professional and Business Services',
            'Alternative Dispute Resolution',
            'Consumer Services',
            'Environmental Services',
            'Executive Office',
            'Facilities Services',
            'Human Resources',
            'International Trade and Development',
            'Law Practice',
            'Legal Services',
            'Management Consulting',
            'Market Research',
            'Outsourcing/Offshoring',
            'Program Development',
            'Research',
            'Security & Investigations',
            'Staffing & Recruiting',
            'Staffing and Recruiting',
            'Think Tanks',
            'Translation & Localization'
        ],
        'Real Estate' => [
            'Commercial Real Estate',
            'Real Estate'
        ],
        'Retail' => [
            'Arts & Crafts',
            'Consumer Goods',
            'Retail',
            'Supermarkets',
            'Wholesale'
        ],
        'Telecommunications' => [
            'Telecommunications',
            'Wireless'
        ],
        'Tourism and Hospitality' => [
            'Events Services',
            'Hospitality',
            'Leisure, Travel & Tourism',
            'Recreational Facilities & Services',
            'Sports',
            'Tourism and Hospitality'
        ],
        'Transportation and Logistics' => [
            'Transportation and Logistics',
            'Aviation & Aerospace',
            'Import & Export',
            'Logistics & Supply Chain',
            'Logistics and Supply Chain',
            'Maritime',
            'Package/Freight Delivery',
            'Transportation/Trucking/Railroad'
        ],
        'Utilities' => []
    ];
}

/**
 * Get flattened list for storage format
 * 
 * @return array Array of 'Category|Sub-Category' strings
 */
function rtr_get_industry_list() {
    $taxonomy = rtr_get_industry_taxonomy();
    $list = [];
    
    foreach ($taxonomy as $category => $subcategories) {
        if (empty($subcategories)) {
            // Top-level category with no subcategories
            $list[] = $category;
        } else {
            // Add each subcategory with pipe separator
            foreach ($subcategories as $subcategory) {
                $list[] = $category . '|' . $subcategory;
            }
        }
    }
    
    sort($list);
    return $list;
}

/**
 * Check if an industry matches a target industry
 * Matches on either category OR sub-category
 * 
 * @param string $visitor_industry Industry from visitor data
 * @param string $target_industry Target industry from rules
 * @return bool
 */
function rtr_industry_matches($visitor_industry, $target_industry) {
    if (empty($visitor_industry) || empty($target_industry)) {
        return false;
    }
    
    // Exact match
    if ($visitor_industry === $target_industry) {
        return true;
    }
    
    // Parse both industries
    $visitor_parts = explode('|', $visitor_industry);
    $target_parts = explode('|', $target_industry);
    
    // Match on top-level category
    if ($visitor_parts[0] === $target_parts[0]) {
        return true;
    }
    
    // Match on sub-category if both have it
    if (isset($visitor_parts[1]) && isset($target_parts[1])) {
        if ($visitor_parts[1] === $target_parts[1]) {
            return true;
        }
    }
    
    return false;
}

/**
 * Get industry categories for dropdown
 * 
 * @return array
 */
function rtr_get_industry_categories() {
    return array_keys(rtr_get_industry_taxonomy());
}

/**
 * Get subcategories for a specific category
 * 
 * @param string $category
 * @return array
 */
function rtr_get_industry_subcategories($category) {
    $taxonomy = rtr_get_industry_taxonomy();
    return isset($taxonomy[$category]) ? $taxonomy[$category] : [];
}

/**
 * Parse industry string into parts
 * 
 * @param string $industry 'Category|Sub-Category' format
 * @return array ['category' => '', 'subcategory' => '']
 */
function rtr_parse_industry($industry) {
    if (empty($industry)) {
        return ['category' => '', 'subcategory' => ''];
    }
    
    $parts = explode('|', $industry);
    return [
        'category' => $parts[0],
        'subcategory' => isset($parts[1]) ? $parts[1] : ''
    ];
}

/**
 * Format industry for display
 * 
 * @param string $industry 'Category|Sub-Category' format
 * @return string Human-readable format
 */
function rtr_format_industry_display($industry) {
    $parts = rtr_parse_industry($industry);
    
    if (empty($parts['subcategory'])) {
        return $parts['category'];
    }
    
    return $parts['category'] . ' → ' . $parts['subcategory'];
}