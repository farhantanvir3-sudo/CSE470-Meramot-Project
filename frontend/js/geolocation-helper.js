/* ============================================================================
 * CLIENT-SIDE MODULE: Geolocation, Haversine & OpenStreetMap Leaflet Picker
 * ============================================================================
 * Work Ownership & Attribution:
 *   1. Shanid Sajjatuz Islam [Student ID: 23201465] (Feature R6):
 *      - Browser GPS positioning, Haversine distance calculation, nearby shop
 *        API queries, and distance badge rendering.
 *   2. Farhan Tanvir [Student ID: 22299179] (Original Meramot Map Picker Port):
 *      - OpenStreetMap + Leaflet interactive map picker modal (`MapPicker`),
 *        Nominatim reverse/forward geocoding (`location-utils`), persistent
 *        location state (`meramot.selectedLocation`), and live form binding.
 *
 * Location: frontend/js/geolocation-helper.js (Served via /js/geolocation-helper.js)
 * Secret Key Required: NONE (100% Open-Source OpenStreetMap + Leaflet + Nominatim)
 * ============================================================================ */

const LOCATION_STORAGE_KEY = 'meramot.selectedLocation';
const DEFAULT_COORDS = [23.8103, 90.4125]; // Dhaka Center

const REVERSE_GEOCODE_ENDPOINT = 'https://nominatim.openstreetmap.org/reverse';
const FORWARD_GEOCODE_ENDPOINT = 'https://nominatim.openstreetmap.org/search';

const BANGLADESH_DISTRICTS = [
    // Dhaka Division (13)
    { city: 'Dhaka', lat: 23.8103, lng: 90.4125, division: 'Dhaka' },
    { city: 'Gazipur', lat: 23.9999, lng: 90.4203, division: 'Dhaka' },
    { city: 'Narayanganj', lat: 23.6238, lng: 90.5000, division: 'Dhaka' },
    { city: 'Tangail', lat: 24.2513, lng: 89.9167, division: 'Dhaka' },
    { city: 'Narsingdi', lat: 23.9322, lng: 90.7154, division: 'Dhaka' },
    { city: 'Munshiganj', lat: 23.5422, lng: 90.5305, division: 'Dhaka' },
    { city: 'Manikganj', lat: 23.8617, lng: 90.0003, division: 'Dhaka' },
    { city: 'Faridpur', lat: 23.6071, lng: 89.8429, division: 'Dhaka' },
    { city: 'Gopalganj', lat: 23.0051, lng: 89.8266, division: 'Dhaka' },
    { city: 'Madaripur', lat: 23.1641, lng: 90.1897, division: 'Dhaka' },
    { city: 'Rajbari', lat: 23.7574, lng: 89.6445, division: 'Dhaka' },
    { city: 'Shariatpur', lat: 23.2423, lng: 90.4348, division: 'Dhaka' },
    { city: 'Kishoreganj', lat: 24.4449, lng: 90.7766, division: 'Dhaka' },
    // Chattogram Division (11)
    { city: 'Chattogram', lat: 22.3569, lng: 91.7832, division: 'Chattogram' },
    { city: "Cox's Bazar", lat: 21.4272, lng: 92.0058, division: 'Chattogram' },
    { city: 'Cumilla', lat: 23.4607, lng: 91.1809, division: 'Chattogram' },
    { city: 'Brahmanbaria', lat: 23.9571, lng: 91.1119, division: 'Chattogram' },
    { city: 'Chandpur', lat: 23.2333, lng: 90.6713, division: 'Chattogram' },
    { city: 'Feni', lat: 23.0159, lng: 91.3976, division: 'Chattogram' },
    { city: 'Noakhali', lat: 22.8696, lng: 91.0994, division: 'Chattogram' },
    { city: 'Lakshmipur', lat: 22.9447, lng: 90.8282, division: 'Chattogram' },
    { city: 'Rangamati', lat: 22.6533, lng: 92.1789, division: 'Chattogram' },
    { city: 'Khagrachhari', lat: 23.1193, lng: 91.9847, division: 'Chattogram' },
    { city: 'Bandarban', lat: 22.1953, lng: 92.2184, division: 'Chattogram' },
    // Sylhet Division (4)
    { city: 'Sylhet', lat: 24.8949, lng: 91.8687, division: 'Sylhet' },
    { city: 'Moulvibazar', lat: 24.4829, lng: 91.7774, division: 'Sylhet' },
    { city: 'Habiganj', lat: 24.3749, lng: 91.4155, division: 'Sylhet' },
    { city: 'Sunamganj', lat: 25.0658, lng: 91.3950, division: 'Sylhet' },
    // Rajshahi Division (8)
    { city: 'Rajshahi', lat: 24.3745, lng: 88.6042, division: 'Rajshahi' },
    { city: 'Bogura', lat: 24.8465, lng: 89.3776, division: 'Rajshahi' },
    { city: 'Pabna', lat: 24.0064, lng: 89.2372, division: 'Rajshahi' },
    { city: 'Sirajganj', lat: 24.4534, lng: 89.7007, division: 'Rajshahi' },
    { city: 'Natore', lat: 24.4206, lng: 89.0003, division: 'Rajshahi' },
    { city: 'Naogaon', lat: 24.7936, lng: 88.9318, division: 'Rajshahi' },
    { city: 'Chapainawabganj', lat: 24.5965, lng: 88.2775, division: 'Rajshahi' },
    { city: 'Joypurhat', lat: 25.0968, lng: 89.0227, division: 'Rajshahi' },
    // Khulna Division (10)
    { city: 'Khulna', lat: 22.8456, lng: 89.5403, division: 'Khulna' },
    { city: 'Jashore', lat: 23.1634, lng: 89.2182, division: 'Khulna' },
    { city: 'Kushtia', lat: 23.9013, lng: 89.1205, division: 'Khulna' },
    { city: 'Satkhira', lat: 22.7185, lng: 89.0705, division: 'Khulna' },
    { city: 'Bagerhat', lat: 22.6516, lng: 89.7859, division: 'Khulna' },
    { city: 'Jhenaidah', lat: 23.5448, lng: 89.1539, division: 'Khulna' },
    { city: 'Magura', lat: 23.4855, lng: 89.4198, division: 'Khulna' },
    { city: 'Chuadanga', lat: 23.6402, lng: 88.8418, division: 'Khulna' },
    { city: 'Meherpur', lat: 23.7622, lng: 88.6318, division: 'Khulna' },
    { city: 'Narail', lat: 23.1725, lng: 89.5127, division: 'Khulna' },
    // Barishal Division (6)
    { city: 'Barishal', lat: 22.7010, lng: 90.3535, division: 'Barishal' },
    { city: 'Patuakhali', lat: 22.3596, lng: 90.3299, division: 'Barishal' },
    { city: 'Bhola', lat: 22.6859, lng: 90.6482, division: 'Barishal' },
    { city: 'Pirojpur', lat: 22.5841, lng: 89.9720, division: 'Barishal' },
    { city: 'Jhalokathi', lat: 22.6406, lng: 90.1987, division: 'Barishal' },
    { city: 'Barguna', lat: 22.1508, lng: 90.1190, division: 'Barishal' },
    // Rangpur Division (8)
    { city: 'Rangpur', lat: 25.7439, lng: 89.2752, division: 'Rangpur' },
    { city: 'Dinajpur', lat: 25.6217, lng: 88.6355, division: 'Rangpur' },
    { city: 'Gaibandha', lat: 25.3288, lng: 89.5281, division: 'Rangpur' },
    { city: 'Kurigram', lat: 25.8054, lng: 89.6362, division: 'Rangpur' },
    { city: 'Lalmonirhat', lat: 25.9923, lng: 89.2847, division: 'Rangpur' },
    { city: 'Nilphamari', lat: 25.9318, lng: 88.8560, division: 'Rangpur' },
    { city: 'Panchagarh', lat: 26.3411, lng: 88.5542, division: 'Rangpur' },
    { city: 'Thakurgaon', lat: 26.0337, lng: 88.4617, division: 'Rangpur' },
    // Mymensingh Division (4)
    { city: 'Mymensingh', lat: 24.7471, lng: 90.4203, division: 'Mymensingh' },
    { city: 'Jamalpur', lat: 24.9375, lng: 89.9378, division: 'Mymensingh' },
    { city: 'Netrokona', lat: 24.8709, lng: 90.7279, division: 'Mymensingh' },
    { city: 'Sherpur', lat: 25.0205, lng: 90.0153, division: 'Mymensingh' }
];

const DISTRICT_ALIASES = {
    'chittagong': 'Chattogram',
    'comilla': 'Cumilla',
    'bogra': 'Bogura',
    'barisal': 'Barishal',
    'jessore': 'Jashore',
    'coxs bazar': "Cox's Bazar",
    "cox's bazar": "Cox's Bazar",
    'chapai nawabganj': 'Chapainawabganj',
    'nawabganj': 'Chapainawabganj',
    'jhalakathi': 'Jhalokathi',
    'netrakona': 'Netrokona',
    'moulovibazar': 'Moulvibazar'
};

const CORE_BANGLADESH_PLACES = [
    // Dhaka: Commercial & Electronics Hubs
    { area: 'Banani', city: 'Dhaka', lat: 23.7937, lng: 90.4066, address: 'Road 11, Banani, Dhaka 1213', keywords: 'banani road 11 kemal ataturk' },
    { area: 'Banani DOHS', city: 'Dhaka', lat: 23.7988, lng: 90.3992, address: 'Banani DOHS, Dhaka 1206' },
    { area: 'Gulshan 1', city: 'Dhaka', lat: 23.7805, lng: 90.4168, address: 'Gulshan 1 Circle, Dhaka 1212', keywords: 'gulshan circle 1 dcc market' },
    { area: 'Gulshan 2', city: 'Dhaka', lat: 23.7925, lng: 90.4158, address: 'Gulshan 2 Circle, Dhaka 1212', keywords: 'gulshan 2 pink city ave' },
    { area: 'Multiplan Center (Elephant Rd)', city: 'Dhaka', lat: 23.7393, lng: 90.3887, address: 'New Elephant Road, Multiplan Center, Dhaka 1205', keywords: 'ecs computer city laptop hardware' },
    { area: 'Hatirpool', city: 'Dhaka', lat: 23.7422, lng: 90.3918, address: 'Hatirpool Eastern Plaza Rd, Dhaka 1205', keywords: 'eastern plaza motaleb plaza mobile market' },
    { area: 'Motaleb Plaza (Hatirpool)', city: 'Dhaka', lat: 23.7453, lng: 90.3941, address: 'Motaleb Plaza, Paribagh, Hatirpool, Dhaka 1205', keywords: 'phone mobile repair plaza' },
    { area: 'BCS Computer City (IDB Bhaban)', city: 'Dhaka', lat: 23.7787, lng: 90.3794, address: 'IDB Bhaban, Agargaon, Dhaka 1207', keywords: 'idb bcs computer city agargaon laptop' },
    { area: 'Bashundhara City (Panthapath)', city: 'Dhaka', lat: 23.7510, lng: 90.3907, address: 'Bashundhara City Complex, Panthapath, Dhaka 1215', keywords: 'bashundhara shopping mobile repair' },
    { area: 'Jamuna Future Park (Kuril)', city: 'Dhaka', lat: 23.8135, lng: 90.4243, address: 'Pragati Sarani, Kuril, Dhaka 1229', keywords: 'jamuna kuril vatara' },
    // Dhaka: Dhanmondi & Central West
    { area: 'Dhanmondi', city: 'Dhaka', lat: 23.7461, lng: 90.3742, address: 'Satmasjid Road, Dhanmondi, Dhaka 1209' },
    { area: 'Dhanmondi 27', city: 'Dhaka', lat: 23.7544, lng: 90.3748, address: 'Road 27 (Old), Dhanmondi, Dhaka 1209' },
    { area: 'Dhanmondi 15', city: 'Dhaka', lat: 23.7443, lng: 90.3735, address: 'Road 15 / Rabindra Sarobar, Dhanmondi, Dhaka 1209' },
    { area: 'Dhanmondi 32', city: 'Dhaka', lat: 23.7518, lng: 90.3775, address: 'Mirpur Road / Dhanmondi 32, Dhaka 1209' },
    { area: 'Kalabagan', city: 'Dhaka', lat: 23.7478, lng: 90.3809, address: 'Kalabagan Bus Stand, Mirpur Rd, Dhaka 1205' },
    { area: 'Green Road', city: 'Dhaka', lat: 23.7458, lng: 90.3863, address: 'Green Road, Dhanmondi, Dhaka 1205' },
    { area: 'Panthapath', city: 'Dhaka', lat: 23.7524, lng: 90.3849, address: 'Panthapath Signal, Dhaka 1215' },
    { area: 'Kathalbagan', city: 'Dhaka', lat: 23.7485, lng: 90.3902, address: 'Kathalbagan Bazaar, Dhaka 1205' },
    { area: 'Lalmatia', city: 'Dhaka', lat: 23.7568, lng: 90.3694, address: 'Block A-G, Lalmatia, Dhaka 1207' },
    { area: 'Jigatola', city: 'Dhaka', lat: 23.7396, lng: 90.3712, address: 'Jigatola Bus Stand, Dhaka 1209' },
    { area: 'New Market', city: 'Dhaka', lat: 23.7333, lng: 90.3836, address: 'Mirpur Road, New Market, Dhaka 1205' },
    { area: 'Science Lab', city: 'Dhaka', lat: 23.7390, lng: 90.3833, address: 'Science Lab More, Dhaka 1205' },
    { area: 'Nilkhet', city: 'Dhaka', lat: 23.7330, lng: 90.3871, address: 'Nilkhet More, Dhaka 1205' },
    { area: 'Azimpur', city: 'Dhaka', lat: 23.7279, lng: 90.3852, address: 'Azimpur Colony More, Dhaka 1205' },
    { area: 'Shahbagh', city: 'Dhaka', lat: 23.7389, lng: 90.3958, address: 'Shahbagh Square, Dhaka 1000' },
    // Dhaka: Uttara Sectors & Airport
    { area: 'Uttara', city: 'Dhaka', lat: 23.8759, lng: 90.3795, address: 'Uttara Commercial Hub, Dhaka 1230' },
    { area: 'Uttara Sector 1', city: 'Dhaka', lat: 23.8602, lng: 90.4005, address: 'Sector 1, Jashimuddin Ave, Uttara, Dhaka 1230' },
    { area: 'Uttara Sector 3', city: 'Dhaka', lat: 23.8648, lng: 90.3965, address: 'Sector 3, Rabindra Sarani, Uttara, Dhaka 1230' },
    { area: 'Uttara Sector 4', city: 'Dhaka', lat: 23.8639, lng: 90.4027, address: 'Sector 4, Road 13, Uttara, Dhaka 1230' },
    { area: 'Uttara Sector 6', city: 'Dhaka', lat: 23.8707, lng: 90.4021, address: 'Sector 6, House Building, Uttara, Dhaka 1230' },
    { area: 'Uttara Sector 7', city: 'Dhaka', lat: 23.8685, lng: 90.3976, address: 'Sector 7, Sonargaon Janapath, Uttara, Dhaka 1230' },
    { area: 'Uttara Sector 9', city: 'Dhaka', lat: 23.8732, lng: 90.3982, address: 'Sector 9, Gareeb-E-Newaz Ave, Uttara, Dhaka 1230' },
    { area: 'Uttara Sector 10', city: 'Dhaka', lat: 23.8842, lng: 90.3877, address: 'Sector 10, Kamarpara Rd, Uttara, Dhaka 1230' },
    { area: 'Uttara Sector 11', city: 'Dhaka', lat: 23.8768, lng: 90.3889, address: 'Sector 11, Gareeb-E-Newaz Ave, Uttara, Dhaka 1230' },
    { area: 'Uttara Sector 12', city: 'Dhaka', lat: 23.8719, lng: 90.3847, address: 'Sector 12, Khalpar, Uttara, Dhaka 1230' },
    { area: 'Uttara Sector 13', city: 'Dhaka', lat: 23.8691, lng: 90.3905, address: 'Sector 13, Sonargaon Janapath, Uttara, Dhaka 1230' },
    { area: 'Uttara Sector 14', city: 'Dhaka', lat: 23.8643, lng: 90.3894, address: 'Sector 14, Gausul Azam Ave, Uttara, Dhaka 1230' },
    { area: 'Uttara Sector 15 (Diabari)', city: 'Dhaka', lat: 23.8744, lng: 90.3665, address: 'Sector 15, Diabari Metro Hub, Uttara, Dhaka 1230' },
    { area: 'Uttara Azampur', city: 'Dhaka', lat: 23.8686, lng: 90.4012, address: 'Azampur Railgate / Sector 7, Uttara, Dhaka 1230' },
    { area: 'Uttara House Building', city: 'Dhaka', lat: 23.8742, lng: 90.4004, address: 'House Building Stoppage, Dhaka Mymensingh Hwy, Dhaka 1230' },
    { area: 'Airport / Kawlar', city: 'Dhaka', lat: 23.8482, lng: 90.4071, address: 'Airport Rd, Kawlar, Dhaka 1229' },
    { area: 'Dakshinkhan', city: 'Dhaka', lat: 23.8608, lng: 90.4222, address: 'Dakshinkhan Bazar Rd, Dhaka 1230' },
    { area: 'Ashkona', city: 'Dhaka', lat: 23.8548, lng: 90.4150, address: 'Ashkona Hajj Camp Rd, Dhaka 1230' },
    { area: 'Khilkhet', city: 'Dhaka', lat: 23.8295, lng: 90.4202, address: 'Khilkhet Overbridge, Dhaka 1229' },
    { area: 'Nikunja 2', city: 'Dhaka', lat: 23.8245, lng: 90.4167, address: 'Nikunja 2 Main Gate, Khilkhet, Dhaka 1229' },
    // Dhaka: Mirpur, Pallabi & Kafrul
    { area: 'Mirpur', city: 'Dhaka', lat: 23.8071, lng: 90.3686, address: 'Mirpur 10 Roundabout, Mirpur, Dhaka 1216' },
    { area: 'Mirpur 1', city: 'Dhaka', lat: 23.7956, lng: 90.3537, address: 'Mirpur 1 Sony Square, Dhaka 1216' },
    { area: 'Mirpur 2', city: 'Dhaka', lat: 23.8049, lng: 90.3612, address: 'Mirpur 2 National Stadium Rd, Dhaka 1216' },
    { area: 'Mirpur 6', city: 'Dhaka', lat: 23.8124, lng: 90.3652, address: 'Mirpur Section 6 Bazar, Dhaka 1216' },
    { area: 'Mirpur 10', city: 'Dhaka', lat: 23.8071, lng: 90.3686, address: 'Mirpur 10 Shah Ali Plaza, Dhaka 1216' },
    { area: 'Mirpur 11', city: 'Dhaka', lat: 23.8185, lng: 90.3654, address: 'Mirpur 11 Bus Stand, Dhaka 1216' },
    { area: 'Mirpur 12 (Pallabi)', city: 'Dhaka', lat: 23.8282, lng: 90.3642, address: 'Mirpur 12 Pallabi Metro Hub, Dhaka 1216' },
    { area: 'Mirpur 14', city: 'Dhaka', lat: 23.7976, lng: 90.3860, address: 'Mirpur 14 Police Staff College Rd, Dhaka 1206' },
    { area: 'Mirpur DOHS', city: 'Dhaka', lat: 23.8364, lng: 90.3698, address: 'Mirpur DOHS Cultural Center Rd, Dhaka 1216' },
    { area: 'Shewrapara', city: 'Dhaka', lat: 23.7885, lng: 90.3755, address: 'West Shewrapara, Begum Rokeya Ave, Dhaka 1216' },
    { area: 'Kazipara', city: 'Dhaka', lat: 23.7970, lng: 90.3728, address: 'Kazipara Bus Stand, Begum Rokeya Ave, Dhaka 1216' },
    { area: 'Taltola (Agargaon)', city: 'Dhaka', lat: 23.7815, lng: 90.3782, address: 'Taltola Market, Agargaon, Dhaka 1207' },
    { area: 'Kafrul / Ibrahimpur', city: 'Dhaka', lat: 23.7916, lng: 90.3878, address: 'Ibrahimpur Main Rd, Kafrul, Dhaka 1206' },
    // Dhaka: Mohammadpur & Adabor
    { area: 'Mohammadpur', city: 'Dhaka', lat: 23.7662, lng: 90.3589, address: 'Town Hall Market, Mohammadpur, Dhaka 1207' },
    { area: 'Mohammadpur Shia Mosque', city: 'Dhaka', lat: 23.7660, lng: 90.3638, address: 'Ring Road, Shia Mosque, Mohammadpur, Dhaka 1207' },
    { area: 'Adabor', city: 'Dhaka', lat: 23.7712, lng: 90.3596, address: 'Adabor Ring Road, Dhaka 1207' },
    { area: 'Shyamoli', city: 'Dhaka', lat: 23.7745, lng: 90.3654, address: 'Shyamoli Square, Mirpur Rd, Dhaka 1207' },
    { area: 'Kallyanpur', city: 'Dhaka', lat: 23.7793, lng: 90.3612, address: 'Kallyanpur Bus Stand, Mirpur Rd, Dhaka 1207' },
    { area: 'Bosila', city: 'Dhaka', lat: 23.7495, lng: 90.3455, address: 'Bosila City Housing Rd, Mohammadpur, Dhaka 1207' },
    // Dhaka: Farmgate, Tejgaon, Karwan Bazar, Ramna & Motijheel
    { area: 'Farmgate', city: 'Dhaka', lat: 23.7561, lng: 90.3872, address: 'Kazi Nazrul Islam Ave, Farmgate, Dhaka 1215' },
    { area: 'Karwan Bazar', city: 'Dhaka', lat: 23.7516, lng: 90.3935, address: 'Karwan Bazar Commercial Circle, Dhaka 1215' },
    { area: 'Tejgaon', city: 'Dhaka', lat: 23.7639, lng: 90.3995, address: 'Tejgaon Industrial Area, Dhaka 1208' },
    { area: 'Mohakhali', city: 'Dhaka', lat: 23.7778, lng: 90.4057, address: 'Mohakhali Wireless / Amtoli, Dhaka 1212' },
    { area: 'Mohakhali DOHS', city: 'Dhaka', lat: 23.7810, lng: 90.3958, address: 'Mohakhali DOHS Gate, Dhaka 1206' },
    { area: 'Niketan', city: 'Dhaka', lat: 23.7749, lng: 90.4119, address: 'Block C, Niketan Society, Dhaka 1212' },
    { area: 'Maghbazar', city: 'Dhaka', lat: 23.7495, lng: 90.4046, address: 'Maghbazar Wireless More, Dhaka 1217' },
    { area: 'Eskaton', city: 'Dhaka', lat: 23.7460, lng: 90.4009, address: 'New Eskaton Rd, Dhaka 1000' },
    { area: 'Malibagh', city: 'Dhaka', lat: 23.7468, lng: 90.4126, address: 'Malibagh Railgate, Dhaka 1217' },
    { area: 'Shantinagar', city: 'Dhaka', lat: 23.7393, lng: 90.4119, address: 'Shantinagar Twin Towers Rd, Dhaka 1217' },
    { area: 'Kakrail', city: 'Dhaka', lat: 23.7376, lng: 90.4079, address: 'Kakrail VIP Road, Dhaka 1000' },
    { area: 'Purana Paltan', city: 'Dhaka', lat: 23.7302, lng: 90.4114, address: 'Purana Paltan More, Dhaka 1000' },
    { area: 'Motijheel', city: 'Dhaka', lat: 23.7295, lng: 90.4178, address: 'Dilkusha / Shapla Chattar, Motijheel, Dhaka 1000' },
    { area: 'Gulistan', city: 'Dhaka', lat: 23.7252, lng: 90.4106, address: 'Baitul Mukarram / Stadium Market, Gulistan, Dhaka 1000' },
    { area: 'Fakirapool', city: 'Dhaka', lat: 23.7347, lng: 90.4167, address: 'Fakirapool Water Tank Rd, Dhaka 1000' },
    // Dhaka: Bashundhara, Baridhara, Badda, Rampura, Khilgaon
    { area: 'Bashundhara R/A', city: 'Dhaka', lat: 23.8191, lng: 90.4526, address: 'Block C, Bashundhara R/A, Dhaka 1229' },
    { area: 'Bashundhara Gate / Apollo', city: 'Dhaka', lat: 23.8138, lng: 90.4316, address: 'Bashundhara Main Gate Rd, Dhaka 1229' },
    { area: 'Baridhara DOHS', city: 'Dhaka', lat: 23.8124, lng: 90.4139, address: 'Baridhara DOHS Main Gate, Dhaka 1206' },
    { area: 'Baridhara J Block', city: 'Dhaka', lat: 23.7998, lng: 90.4229, address: 'Baridhara J Block, Madani Ave, Dhaka 1212' },
    { area: 'Shahjadpur', city: 'Dhaka', lat: 23.7918, lng: 90.4256, address: 'Shahjadpur Bus Stand, Pragati Sarani, Dhaka 1212' },
    { area: 'Notun Bazar', city: 'Dhaka', lat: 23.7975, lng: 90.4243, address: 'Notun Bazar 100ft Rd, Gulshan, Dhaka 1212' },
    { area: 'Badda', city: 'Dhaka', lat: 23.7805, lng: 90.4267, address: 'Middle Badda, Pragati Sarani, Dhaka 1212' },
    { area: 'Badda Link Road', city: 'Dhaka', lat: 23.7807, lng: 90.4220, address: 'Gulshan Badda Link Road, Dhaka 1212' },
    { area: 'North Badda', city: 'Dhaka', lat: 23.7867, lng: 90.4256, address: 'North Badda Hossain Market, Dhaka 1212' },
    { area: 'Rampura', city: 'Dhaka', lat: 23.7612, lng: 90.4219, address: 'Rampura TV Center Road, Dhaka 1219' },
    { area: 'Banasree', city: 'Dhaka', lat: 23.7634, lng: 90.4318, address: 'Block C, Main Road, Banasree, Dhaka 1219' },
    { area: 'South Banasree', city: 'Dhaka', lat: 23.7576, lng: 90.4368, address: 'South Banasree Project Rd, Dhaka 1219' },
    { area: 'Aftabnagar', city: 'Dhaka', lat: 23.7639, lng: 90.4398, address: 'Aftabnagar Main Gate, Badda, Dhaka 1212' },
    { area: 'Khilgaon Taltola', city: 'Dhaka', lat: 23.7524, lng: 90.4262, address: 'Khilgaon Taltola Market, Dhaka 1219' },
    { area: 'Basabo', city: 'Dhaka', lat: 23.7438, lng: 90.4278, address: 'Sabujbagh / Basabo Balur Math, Dhaka 1214' },
    { area: 'Mugda', city: 'Dhaka', lat: 23.7336, lng: 90.4314, address: 'Mugda Medical College Rd, Dhaka 1214' },
    // Dhaka: Old Dhaka, South & Suburbs
    { area: 'Wari', city: 'Dhaka', lat: 23.7176, lng: 90.4178, address: 'Rankin Street, Wari, Dhaka 1203' },
    { area: 'Patuatuli (Islampur)', city: 'Dhaka', lat: 23.7088, lng: 90.4090, address: 'Patuatuli Electronics Market, Old Dhaka 1100', keywords: 'patuatuli electronics accessories' },
    { area: 'Sadarghat', city: 'Dhaka', lat: 23.7052, lng: 90.4102, address: 'Johnson Road, Sadarghat, Dhaka 1100' },
    { area: 'Sutrapur', city: 'Dhaka', lat: 23.7111, lng: 90.4150, address: 'Sutrapur Dholaikhal Rd, Dhaka 1100' },
    { area: 'Chawkbazar (Old Dhaka)', city: 'Dhaka', lat: 23.7145, lng: 90.3965, address: 'Chawkbazar Shahi Mosque Rd, Dhaka 1211' },
    { area: 'Lalbagh', city: 'Dhaka', lat: 23.7189, lng: 90.3881, address: 'Lalbagh Fort Road, Dhaka 1211' },
    { area: 'Jatrabari', city: 'Dhaka', lat: 23.7104, lng: 90.4352, address: 'Jatrabari Flyover Chowrasta, Dhaka 1204' },
    { area: 'Sayedabad', city: 'Dhaka', lat: 23.7165, lng: 90.4292, address: 'Sayedabad Bus Terminal Rd, Dhaka 1204' },
    { area: 'Jurain', city: 'Dhaka', lat: 23.6946, lng: 90.4322, address: 'Jurain Railgate, Dhaka 1204' },
    { area: 'Demra', city: 'Dhaka', lat: 23.7204, lng: 90.4954, address: 'Demra Staff Quarter Chowrasta, Dhaka 1361' },
    { area: 'Savar Bazar', city: 'Dhaka', lat: 23.8583, lng: 90.2667, address: 'Savar Bazar Bus Stand, Dhaka 1340' },
    { area: 'Ashulia / Zirabo', city: 'Dhaka', lat: 23.9038, lng: 90.3125, address: 'Ashulia Bazar Rd, Savar, Dhaka 1341' },
    { area: 'Keraniganj', city: 'Dhaka', lat: 23.6852, lng: 90.3461, address: 'Kadamtali / Zinzira, Keraniganj, Dhaka 1310' },
    // Gazipur
    { area: 'Gazipur Chowrasta', city: 'Gazipur', lat: 23.9888, lng: 90.3845, address: 'Chandana Chowrasta, Gazipur 1702' },
    { area: 'Tongi Station Road', city: 'Gazipur', lat: 23.8975, lng: 90.4024, address: 'Tongi Bazar / Station Rd, Gazipur 1710' },
    { area: 'Board Bazar', city: 'Gazipur', lat: 23.9469, lng: 90.3834, address: 'Board Bazar, National University Rd, Gazipur 1704' },
    { area: 'Konabari', city: 'Gazipur', lat: 24.0005, lng: 90.3168, address: 'Konabari BSCIC Rd, Gazipur 1700' },
    { area: 'Joydebpur', city: 'Gazipur', lat: 23.9999, lng: 90.4203, address: 'Joydebpur Bazar, Gazipur Sadar 1700' },
    { area: 'Sreepur / Mawna', city: 'Gazipur', lat: 24.1982, lng: 90.4638, address: 'Mawna Chowrasta, Sreepur, Gazipur 1740' },
    { area: 'Kaliakoir', city: 'Gazipur', lat: 24.0752, lng: 90.2194, address: 'Kaliakoir Bazar, Gazipur 1750' },
    // Narayanganj
    { area: 'Chashara', city: 'Narayanganj', lat: 23.6226, lng: 90.4998, address: 'Bangabandhu Road, Chashara, Narayanganj 1400' },
    { area: 'Narayanganj 2 No. Railgate', city: 'Narayanganj', lat: 23.6184, lng: 90.5048, address: '2 No. Railgate Electronics Market, Narayanganj 1400' },
    { area: 'Signboard', city: 'Narayanganj', lat: 23.6946, lng: 90.4793, address: 'Signboard Mor, Dhaka-Chattogram Hwy, Narayanganj' },
    { area: 'Siddhirganj', city: 'Narayanganj', lat: 23.6712, lng: 90.5179, address: 'Chittagong Road, Siddhirganj, Narayanganj 1430' },
    { area: 'Fatullah', city: 'Narayanganj', lat: 23.6496, lng: 90.4832, address: 'Fatullah Post Office Rd, Narayanganj 1420' },
    { area: 'Bhulta / Rupganj', city: 'Narayanganj', lat: 23.7924, lng: 90.5621, address: 'Bhulta Gausia Market, Rupganj, Narayanganj 1462' },
    // Chattogram
    { area: 'Agrabad', city: 'Chattogram', lat: 22.3264, lng: 91.8155, address: 'Agrabad Commercial Area, Chattogram 4100' },
    { area: 'GEC Circle', city: 'Chattogram', lat: 22.3592, lng: 91.8219, address: 'CDA Avenue, GEC Circle, Chattogram 4000' },
    { area: 'Jubilee Road (Computer Market)', city: 'Chattogram', lat: 22.3401, lng: 91.8286, address: 'Jubilee Road RF Tower / Computer City, Chattogram 4000' },
    { area: '2 No. Gate', city: 'Chattogram', lat: 22.3658, lng: 91.8235, address: '2 No. Gate Circle, Nasirabad, Chattogram 4203' },
    { area: 'Nasirabad', city: 'Chattogram', lat: 22.3627, lng: 91.8181, address: 'Nasirabad Housing Society, Chattogram 4203' },
    { area: 'Muradpur', city: 'Chattogram', lat: 22.3694, lng: 91.8321, address: 'Muradpur Junction, Chattogram 4212' },
    { area: 'Chawkbazar (Ctg)', city: 'Chattogram', lat: 22.3578, lng: 91.8388, address: 'Chawkbazar Gulzar Tower, Chattogram 4203' },
    { area: 'Halishahar', city: 'Chattogram', lat: 22.3237, lng: 91.7878, address: 'Halishahar Housing Estate B-Block, Chattogram 4216' },
    { area: 'New Market (Ctg)', city: 'Chattogram', lat: 22.3347, lng: 91.8336, address: 'Bipani Bitan / New Market, Chattogram 4000' },
    { area: 'WASA Circle', city: 'Chattogram', lat: 22.3526, lng: 91.8211, address: 'WASA More, Dampara, Chattogram 4000' },
    { area: 'Bahaddarhat', city: 'Chattogram', lat: 22.3717, lng: 91.8442, address: 'Bahaddarhat Bus Terminal More, Chattogram 4212' },
    { area: 'Lalkhan Bazar', city: 'Chattogram', lat: 22.3490, lng: 91.8172, address: 'Lalkhan Bazar Flyover Circle, Chattogram 4000' },
    { area: 'Patenga', city: 'Chattogram', lat: 22.2449, lng: 91.7924, address: 'Katgor / Patenga Rd, Chattogram 4204' },
    // Sylhet
    { area: 'Zindabazar', city: 'Sylhet', lat: 24.8949, lng: 91.8687, address: 'Zindabazar Main Road / Karimullah Market, Sylhet 3100' },
    { area: 'Amberkhana', city: 'Sylhet', lat: 24.9048, lng: 91.8693, address: 'Amberkhana Point, Sylhet 3100' },
    { area: 'Bondor Bazar', city: 'Sylhet', lat: 24.8891, lng: 91.8722, address: 'Bondor Bazar, Hassan Market, Sylhet 3100' },
    { area: 'Chouhatta', city: 'Sylhet', lat: 24.8998, lng: 91.8685, address: 'Chouhatta Point, Sylhet 3100' },
    { area: 'Shahjalal Upashahar', city: 'Sylhet', lat: 24.8886, lng: 91.8902, address: 'Block C, Shahjalal Upashahar, Sylhet 3100' },
    { area: 'Shibgonj', city: 'Sylhet', lat: 24.8942, lng: 91.8861, address: 'Shibgonj Bazar Point, Sylhet 3100' },
    { area: 'Subidbazar', city: 'Sylhet', lat: 24.9062, lng: 91.8559, address: 'Subidbazar Point, Airport Rd, Sylhet 3100' },
    { area: 'Kumarpara', city: 'Sylhet', lat: 24.8944, lng: 91.8781, address: 'Kumarpara Road, Sylhet 3100' },
    // Rajshahi
    { area: 'Shaheb Bazar', city: 'Rajshahi', lat: 24.3653, lng: 88.6003, address: 'Shaheb Bazar Zero Point, Rajshahi 6100' },
    { area: 'New Market (Rajshahi)', city: 'Rajshahi', lat: 24.3712, lng: 88.6041, address: 'Shalbagan / Rajshahi New Market, Rajshahi 6000' },
    { area: 'Laxmipur', city: 'Rajshahi', lat: 24.3728, lng: 88.5835, address: 'Laxmipur More, Rajshahi 6000' },
    { area: 'Talaimari', city: 'Rajshahi', lat: 24.3612, lng: 88.6272, address: 'Talaimari More, Rajshahi 6204' },
    { area: 'Uposhohor', city: 'Rajshahi', lat: 24.3821, lng: 88.6056, address: 'Housing Estate, Uposhohor, Rajshahi 6202' },
    { area: 'Kazla', city: 'Rajshahi', lat: 24.3676, lng: 88.6368, address: 'Kazla Gate, University Rd, Rajshahi 6205' },
    // Khulna
    { area: 'Shib Bari More', city: 'Khulna', lat: 22.8206, lng: 89.5535, address: 'Shib Bari Circle, KDA Ave, Khulna 9100' },
    { area: 'Picture Palace More', city: 'Khulna', lat: 22.8142, lng: 89.5618, address: 'Picture Palace More, Khulna 9100' },
    { area: 'Daulatpur', city: 'Khulna', lat: 22.8744, lng: 89.5204, address: 'Daulatpur Bus Stand, Khulna 9202' },
    { area: 'Khalishpur', city: 'Khulna', lat: 22.8511, lng: 89.5404, address: 'New Market, Khalishpur, Khulna 9000' },
    { area: 'Sonadanga', city: 'Khulna', lat: 22.8235, lng: 89.5416, address: 'Sonadanga Bus Terminal Rd, Khulna 9100' },
    { area: 'Gollamari', city: 'Khulna', lat: 22.8012, lng: 89.5412, address: 'Gollamari More, Khulna 9208' },
    // Other Divisional & Major Cities
    { area: 'Kandirpar', city: 'Cumilla', lat: 23.4612, lng: 91.1815, address: 'Kandirpar Pubali Chattar, Cumilla 3500' },
    { area: 'Tomsom Bridge', city: 'Cumilla', lat: 23.4488, lng: 91.1795, address: 'Tomsom Bridge More, Cumilla 3500' },
    { area: 'Jhautala', city: 'Cumilla', lat: 23.4578, lng: 91.1764, address: 'Jhautala Main Rd, Cumilla 3500' },
    { area: 'Police Lines', city: 'Cumilla', lat: 23.4651, lng: 91.1743, address: 'Police Lines Gate, Cumilla 3500' },
    { area: 'Satmatha', city: 'Bogura', lat: 24.8481, lng: 89.3730, address: 'Satmatha Zero Point, Bogura 5800' },
    { area: 'Jaleswaritola', city: 'Bogura', lat: 24.8442, lng: 89.3788, address: 'Jaleswaritola Main Rd, Bogura 5800' },
    { area: 'Thanthania', city: 'Bogura', lat: 24.8351, lng: 89.3655, address: 'Thanthania Bus Stand, Bogura 5800' },
    { area: 'Sherpur Road', city: 'Bogura', lat: 24.8390, lng: 89.3712, address: 'Sherpur Rd Computer Market, Bogura 5800' },
    { area: 'Sadar Road (Barishal)', city: 'Barishal', lat: 22.7010, lng: 90.3535, address: 'Sadar Road / Town Hall, Barishal 8200' },
    { area: 'Nathullabad', city: 'Barishal', lat: 22.7248, lng: 90.3478, address: 'Nathullabad Central Bus Terminal, Barishal 8200' },
    { area: 'Rupatali', city: 'Barishal', lat: 22.6819, lng: 90.3442, address: 'Rupatali Chowmatha, Barishal 8207' },
    { area: 'Bangla Bazar (Barishal)', city: 'Barishal', lat: 22.7042, lng: 90.3602, address: 'Bangla Bazar More, Barishal 8200' },
    { area: 'Jahaj Company More', city: 'Rangpur', lat: 25.7468, lng: 89.2504, address: 'Station Road, Jahaj Company More, Rangpur 5400' },
    { area: 'Shapla Chattar', city: 'Rangpur', lat: 25.7362, lng: 89.2478, address: 'Shapla Chattar, Rangpur 5400' },
    { area: 'Medical More', city: 'Rangpur', lat: 25.7642, lng: 89.2335, address: 'Medical College More, Rangpur 5400' },
    { area: 'Lalbagh (Rangpur)', city: 'Rangpur', lat: 25.7245, lng: 89.2542, address: 'Lalbagh Bazar, Rangpur 5402' },
    { area: 'Ganginar Par', city: 'Mymensingh', lat: 24.7568, lng: 90.4055, address: 'Ganginar Par Traffic More, Mymensingh 2200' },
    { area: 'Charpara', city: 'Mymensingh', lat: 24.7452, lng: 90.4102, address: 'Charpara Medical More, Mymensingh 2200' },
    { area: 'Town Hall More', city: 'Mymensingh', lat: 24.7595, lng: 90.4018, address: 'Town Hall Circle, Mymensingh 2200' },
    { area: 'Maskanda', city: 'Mymensingh', lat: 24.7295, lng: 90.4085, address: 'Maskanda Bus Terminal, Mymensingh 2200' },
    { area: 'Kalatoli', city: "Cox's Bazar", lat: 21.4187, lng: 91.9841, address: "Kalatoli Dolphin More, Cox's Bazar 4700" },
    { area: 'Laboni Point', city: "Cox's Bazar", lat: 21.4272, lng: 91.9754, address: "Laboni Beach Rd, Cox's Bazar 4700" },
    { area: 'Bazarghata / Town Hall', city: "Cox's Bazar", lat: 21.4408, lng: 91.9794, address: "Main Rd, Bazarghata, Cox's Bazar 4700" },
    { area: 'Doratana', city: 'Jashore', lat: 23.1684, lng: 89.2111, address: 'Doratana Zero Point, Jashore 7400' },
    { area: 'Chachra', city: 'Jashore', lat: 23.1502, lng: 89.1985, address: 'Chachra Checkpost, Jashore 7400' },
    { area: 'New Market (Jashore)', city: 'Jashore', lat: 23.1722, lng: 89.2185, address: 'R.N. Road / New Market, Jashore 7400' },
    { area: 'Nirala More', city: 'Tangail', lat: 24.2496, lng: 89.9162, address: 'Nirala More Main Market, Tangail 1900' },
    { area: 'Victoria Road', city: 'Tangail', lat: 24.2535, lng: 89.9141, address: 'Victoria Road Electronics Hub, Tangail 1900' },
    { area: 'Velanagar', city: 'Narsingdi', lat: 23.9225, lng: 90.7068, address: 'Velanagar Bazar Circle, Narsingdi 1600' },
    { area: 'Madhabdi', city: 'Narsingdi', lat: 23.8482, lng: 90.6745, address: 'Madhabdi Bus Stand, Narsingdi 1604' },
    { area: 'Lilir More', city: 'Dinajpur', lat: 25.6274, lng: 88.6331, address: 'Lilir More Station Rd, Dinajpur 5200' },
    { area: 'Majampur Gate', city: 'Kushtia', lat: 23.9022, lng: 89.1195, address: 'N.S. Road, Majampur Gate, Kushtia 7000' },
    { area: 'Mohipal', city: 'Feni', lat: 23.0086, lng: 91.3845, address: 'Mohipal Circle, Feni 3900' },
    { area: 'Trunk Road (Feni)', city: 'Feni', lat: 23.0165, lng: 91.3976, address: 'Trunk Road Zero Point, Feni 3900' },
    { area: 'Maijdee Court', city: 'Noakhali', lat: 22.8722, lng: 91.0975, address: 'Main Road, Maijdee Court, Noakhali 3800' },
    { area: 'Chowmuhani', city: 'Noakhali', lat: 22.9425, lng: 91.1192, address: 'Chowmuhani Station Rd, Noakhali 3821' },
    { area: 'Abdul Hamid Road', city: 'Pabna', lat: 24.0068, lng: 89.2368, address: 'Abdul Hamid Rd Market, Pabna 6600' },
    { area: 'Mujib Sarak', city: 'Faridpur', lat: 23.6062, lng: 89.8404, address: 'Mujib Sarak / Janatar More, Faridpur 7800' },
    { area: 'Kalibari More', city: 'Brahmanbaria', lat: 23.9571, lng: 91.1128, address: 'T.A. Road / Kalibari More, Brahmanbaria 3400' },
    { area: 'Bazar Road (Sirajganj)', city: 'Sirajganj', lat: 24.4552, lng: 89.7014, address: 'S.S. Road Electronics Hub, Sirajganj 6700' },
    { area: 'Gauripur / Gurudayal', city: 'Kishoreganj', lat: 24.4422, lng: 90.7768, address: 'Kalibari / Station Rd, Kishoreganj 2300' },
    { area: 'Kadirabad / Kalihati Rd', city: 'Jamalpur', lat: 24.9375, lng: 89.9412, address: 'Dayamoyee More, Jamalpur 2000' }
];

const BANGLADESH_PLACES_CATALOG = (() => {
    const list = CORE_BANGLADESH_PLACES.map((item) => ({
        ...item,
        searchText: `${item.area} ${item.city} ${item.address} ${item.keywords || ''}`.toLowerCase()
    }));
    const existingAreasByCity = new Map();
    for (const item of list) {
        const key = item.city.toLowerCase();
        if (!existingAreasByCity.has(key)) existingAreasByCity.set(key, new Set());
        existingAreasByCity.get(key).add(item.area.toLowerCase());
    }
    for (const dist of BANGLADESH_DISTRICTS) {
        const cityKey = dist.city.toLowerCase();
        const have = existingAreasByCity.get(cityKey) || new Set();
        if (have.size < 3) {
            const presets = [
                { suffix: 'Sadar', address: `${dist.city} Sadar Town Center`, dLat: 0, dLng: 0 },
                { suffix: 'Station Road / Main Bazar', address: `Main Bazar & Station Road, ${dist.city}`, dLat: 0.004, dLng: 0.004 },
                { suffix: 'Chowrasta / Bypass', address: `Bypass Circle, ${dist.city}`, dLat: -0.005, dLng: -0.004 }
            ];
            for (const preset of presets) {
                const area = `${dist.city} ${preset.suffix}`;
                if (!have.has(area.toLowerCase())) {
                    have.add(area.toLowerCase());
                    list.push({
                        area,
                        city: dist.city,
                        lat: Number((dist.lat + preset.dLat).toFixed(5)),
                        lng: Number((dist.lng + preset.dLng).toFixed(5)),
                        address: preset.address,
                        searchText: `${area} ${dist.city} ${preset.address} ${dist.division}`.toLowerCase()
                    });
                }
            }
        }
    }
    return list;
})();

const DHAKA_HUBS = BANGLADESH_PLACES_CATALOG.slice(0, 19);

const MeramotLocationCatalog = {
    districts: BANGLADESH_DISTRICTS,
    places: BANGLADESH_PLACES_CATALOG,

    getDistricts() {
        return BANGLADESH_DISTRICTS.map((d) => d.city);
    },

    findDistrict(rawCity) {
        const trimmed = String(rawCity || '').trim();
        if (!trimmed) return 'Dhaka';
        const lower = trimmed.toLowerCase();
        if (DISTRICT_ALIASES[lower]) return DISTRICT_ALIASES[lower];
        const exact = BANGLADESH_DISTRICTS.find((d) => d.city.toLowerCase() === lower);
        if (exact) return exact.city;
        const partial = BANGLADESH_DISTRICTS.find(
            (d) => lower.includes(d.city.toLowerCase()) || d.city.toLowerCase().includes(lower)
        );
        return partial ? partial.city : trimmed;
    },

    getDistrictCoords(city) {
        const canonical = this.findDistrict(city);
        const match = BANGLADESH_DISTRICTS.find((d) => d.city.toLowerCase() === canonical.toLowerCase());
        return match || BANGLADESH_DISTRICTS[0];
    },

    getPlacesByCity(city, ensureExtraArea = '') {
        const canonical = this.findDistrict(city);
        const results = BANGLADESH_PLACES_CATALOG.filter(
            (p) => p.city.toLowerCase() === canonical.toLowerCase()
        );
        const extra = String(ensureExtraArea || '').trim();
        if (extra && !results.some((p) => p.area.toLowerCase() === extra.toLowerCase())) {
            const base = this.getDistrictCoords(canonical);
            results.unshift({
                area: extra,
                city: canonical,
                lat: base.lat,
                lng: base.lng,
                address: `${extra}, ${canonical}`,
                searchText: `${extra} ${canonical}`.toLowerCase()
            });
        }
        return results;
    },

    search(query, cityBias = '', limit = 12) {
        const clean = String(query || '').trim().toLowerCase();
        const canonicalBias = cityBias ? this.findDistrict(cityBias).toLowerCase() : '';
        if (!clean) {
            return canonicalBias
                ? this.getPlacesByCity(cityBias).slice(0, limit)
                : BANGLADESH_PLACES_CATALOG.slice(0, limit);
        }
        const tokens = clean.split(/[\s,]+/).filter(Boolean);
        const scored = [];
        for (const place of BANGLADESH_PLACES_CATALOG) {
            const hay = place.searchText;
            let matchedAll = true;
            for (const tok of tokens) {
                if (!hay.includes(tok)) {
                    matchedAll = false;
                    break;
                }
            }
            if (!matchedAll) continue;
            let score = 0;
            const areaLower = place.area.toLowerCase();
            if (areaLower === clean) score += 120;
            else if (areaLower.startsWith(clean)) score += 75;
            else if (areaLower.includes(clean)) score += 45;
            if (canonicalBias && place.city.toLowerCase() === canonicalBias) score += 35;
            scored.push({ place, score });
        }
        scored.sort((a, b) => b.score - a.score || a.place.area.localeCompare(b.place.area));
        return scored.slice(0, limit).map((row) => ({ ...row.place, source: 'catalog' }));
    }
};

/* ============================================================================
 * SECTION 1: Geolocation & Haversine Helper
 * Work Ownership: Shanid Sajjatuz Islam [Student ID: 23201465] (Feature R6)
 * ============================================================================ */
const MeramotGeolocation = {
    /**
     * Check if browser geolocation is supported
     */
    isSupported() {
        return 'geolocation' in navigator;
    },

    /**
     * Get user's current GPS coordinates
     */
    requestPosition(options) {
        return new Promise((resolve, reject) => {
            if (!this.isSupported()) {
                const err = new Error('GPS is not available in this browser.');
                err.code = 0;
                reject(err);
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    resolve({
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                        accuracy: position.coords.accuracy
                    });
                },
                (error) => {
                    let message = 'Unable to get your location.';
                    switch (error.code) {
                        case error.PERMISSION_DENIED:
                            message = 'Location access is disabled. Tap anywhere on the map or search an area to set your address.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            message = 'Current location is unavailable. Tap anywhere on the map to pin your location.';
                            break;
                        case error.TIMEOUT:
                            message = 'Location lookup timed out. Tap anywhere on the map to pin your location.';
                            break;
                    }
                    const err = new Error(message);
                    err.code = error.code;
                    reject(err);
                },
                options
            );
        });
    },

    async getCurrentPosition() {
        try {
            return await this.requestPosition({
                enableHighAccuracy: true,
                timeout: 4500,
                maximumAge: 300000 // 5 minutes cache
            });
        } catch (primaryError) {
            if (primaryError && primaryError.code === 1) {
                throw primaryError;
            }
            // Indoor / mobile coarse network-based fallback when high-accuracy GPS times out or fails
            return await this.requestPosition({
                enableHighAccuracy: false,
                timeout: 5000,
                maximumAge: 600000 // 10 minutes cache
            });
        }
    },

    /**
     * Calculate distance between two points (Haversine formula in km)
     */
    calculateDistance(lat1, lng1, lat2, lng2) {
        const R = 6371;
        const dLat = this.toRad(lat2 - lat1);
        const dLng = this.toRad(lng2 - lng1);

        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                  Math.cos(this.toRad(lat1)) * Math.cos(this.toRad(lat2)) *
                  Math.sin(dLng / 2) * Math.sin(dLng / 2);

        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    },

    toRad(degrees) {
        return degrees * (Math.PI / 180);
    },

    formatDistance(distanceKm) {
        if (distanceKm < 1) {
            return Math.round(distanceKm * 1000) + 'm';
        }
        return distanceKm.toFixed(1) + 'km';
    },

    estimateTravelTime(distanceKm) {
        const averageSpeedKmh = 15;
        const minutes = Math.round((distanceKm / averageSpeedKmh) * 60);
        return Math.max(10, minutes);
    },

    formatEta(minutes) {
        const mins = Math.max(10, Math.round(minutes));
        if (mins < 60) {
            return `${mins} min`;
        }
        const hours = Math.floor(mins / 60);
        const rem = mins % 60;
        if (rem === 0 || hours >= 4) {
            return `${Math.round(mins / 60)} hr`;
        }
        return `${hours} hr ${rem} min`;
    },

    async fetchNearbyShops(lat, lng, radiusKm = 10) {
        const response = await fetch(`/api/shops/nearby?lat=${lat}&lng=${lng}&radius=${radiusKm}`);
        return await response.json();
    },

    buildShopsUrlWithLocation(lat, lng, maxDistanceKm = null) {
        const url = new URL(window.location.href);
        if (!url.pathname.startsWith('/shops')) {
            url.pathname = '/shops';
            url.search = '';
        }
        const resolvedRadius = maxDistanceKm !== null && maxDistanceKm !== undefined
            ? maxDistanceKm
            : (url.searchParams.get('maxDistanceKm') || 12);

        // Clear confining district/area text filters so Near Me includes all nearby shops across boundaries
        url.searchParams.delete('city');
        url.searchParams.delete('area');
        url.searchParams.set('lat', Number(lat).toFixed(5));
        url.searchParams.set('lng', Number(lng).toFixed(5));
        url.searchParams.set('maxDistanceKm', String(resolvedRadius));
        url.searchParams.set('sort', 'distance');
        url.searchParams.set('page', '1');
        return url.toString();
    },

    updateUrlWithLocation(lat, lng, maxDistanceKm = 12) {
        const nextUrl = this.buildShopsUrlWithLocation(lat, lng, maxDistanceKm);
        if (window.MeramotSoftNav && window.location.pathname.startsWith('/shops')) {
            window.MeramotSoftNav.visit(nextUrl);
        } else {
            window.location.href = nextUrl;
        }
    },

    clearNearMeFilter() {
        const url = new URL(window.location.href);
        url.searchParams.delete('maxDistanceKm');
        url.searchParams.delete('max_distance_km');
        url.searchParams.delete('lat');
        url.searchParams.delete('lng');
        if (url.searchParams.get('sort') === 'distance') {
            url.searchParams.delete('sort');
        }
        url.searchParams.set('page', '1');
        const nextUrl = url.toString();
        if (window.MeramotSoftNav && window.location.pathname.startsWith('/shops')) {
            window.MeramotSoftNav.visit(nextUrl);
        } else {
            window.location.href = nextUrl;
        }
    },

    async enableDistanceFilter(triggerBtn = null) {
        const urlParams = new URLSearchParams(window.location.search);
        const alreadyActive = triggerBtn?.dataset?.nearMeActive === 'true'
            || (window.location.pathname.startsWith('/shops')
                && (urlParams.has('maxDistanceKm')
                    || (urlParams.get('sort') === 'distance' && urlParams.has('lat') && urlParams.has('lng'))));

        if (alreadyActive && window.location.pathname.startsWith('/shops')) {
            this.clearNearMeFilter();
            return;
        }

        try {
            const position = await this.getCurrentPosition();
            this.updateUrlWithLocation(position.lat, position.lng);
            // Persist resolved coordinates & neighborhood asynchronously without triggering a second reload
            if (window.MeramotLocationPicker && typeof MeramotLocationPicker.reverseGeocode === 'function') {
                MeramotLocationPicker.reverseGeocode(position.lat, position.lng, 'gps')
                    .then((loc) => {
                        if (loc) {
                            MeramotLocationPicker.saveLocation({ ...loc, _skipUrlSync: true });
                        }
                    })
                    .catch(() => {});
            }
        } catch (error) {
            const stored = MeramotLocationPicker.parseStoredLocation();
            if (stored && typeof stored.lat === 'number' && typeof stored.lng === 'number') {
                if (window.MeramotToast) {
                    window.MeramotToast.show('Using your pinned location for nearby shops.', 'info');
                }
                this.updateUrlWithLocation(stored.lat, stored.lng);
                return;
            }
            if (window.MeramotToast) {
                window.MeramotToast.show(error.message || 'Please pin your location on the map to find nearby shops.', 'warning');
            }
            window.dispatchEvent(new CustomEvent('meramot-open-location-modal'));
        }
    }
};

/* ============================================================================
 * SECTION 2: OpenStreetMap + Leaflet Location Picker & Nominatim Geocoder
 * Work Ownership: Farhan Tanvir [Student ID: 22299179]
 * Ported from Meramot LocationPickerModal.tsx, MapPicker.tsx & location-utils.ts
 * ============================================================================ */
const MeramotLocationPicker = {
    mapInstance: null,
    markerInstance: null,
    resizeObserver: null,
    draftLocation: null,
    callbacks: {},
    DHAKA_HUBS,

    /**
     * Ensure Leaflet CSS & JS are loaded in the browser even if primary CDN was delayed
     */
    async ensureLeafletLoaded() {
        if (typeof window.L !== 'undefined') return window.L;

        return new Promise((resolve, reject) => {
            if (!document.querySelector('link[href*="leaflet"]')) {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css';
                document.head.appendChild(link);
            }

            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js';
            script.onload = () => resolve(window.L);
            script.onerror = () => {
                const fallback = document.createElement('script');
                fallback.src = 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js';
                fallback.onload = () => resolve(window.L);
                fallback.onerror = () => reject(new Error('Could not load Leaflet map library.'));
                document.head.appendChild(fallback);
            };
            document.head.appendChild(script);
        });
    },

    /**
     * Build compact navbar label from stored location object
     */
    buildLocationLabel(location) {
        if (!location) return 'Choose your location';

        const area = (location.area || '').trim();
        const city = (location.city || '').trim();
        const address = (location.address || '').trim();

        if (area && city) return `${area}, ${city}`;
        if (area) return area;
        if (city) return city;
        if (address) return address.length > 34 ? `${address.slice(0, 34)}...` : address;

        if (typeof location.lat === 'number' && typeof location.lng === 'number') {
            return `${location.lat.toFixed(4)}, ${location.lng.toFixed(4)}`;
        }

        return 'Choose your location';
    },

    /**
     * Read saved location from localStorage
     */
    parseStoredLocation() {
        if (typeof window === 'undefined') return null;

        try {
            const raw = localStorage.getItem(LOCATION_STORAGE_KEY);
            if (!raw) return null;

            const parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== 'object') return null;

            return parsed;
        } catch (e) {
            localStorage.removeItem(LOCATION_STORAGE_KEY);
            return null;
        }
    },

    /**
     * Match raw Nominatim area/address text to closest known Dhaka neighborhood
     */
    inferDhakaArea(rawArea, displayName, lat, lng) {
        const haystack = `${rawArea || ''} ${displayName || ''}`.toLowerCase();
        for (const hub of BANGLADESH_PLACES_CATALOG) {
            if (haystack.includes(hub.area.toLowerCase())) {
                return hub.area;
            }
        }

        if (rawArea && rawArea.trim()) {
            return rawArea.trim();
        }

        if (typeof lat === 'number' && typeof lng === 'number') {
            let bestHub = null;
            let bestDist = Infinity;
            for (const hub of BANGLADESH_PLACES_CATALOG) {
                const d = MeramotGeolocation.calculateDistance(lat, lng, hub.lat, hub.lng);
                if (d < bestDist) {
                    bestDist = d;
                    bestHub = hub;
                }
            }
            if (bestHub && bestDist <= 5.0) {
                return bestHub.area;
            }
        }

        return '';
    },

    /**
     * Reverse geocode [lat, lng] using OpenStreetMap Nominatim (No API key needed)
     */
    async reverseGeocode(lat, lng, source = 'map') {
        try {
            const res = await fetch(
                `${REVERSE_GEOCODE_ENDPOINT}?lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lng)}&format=json&addressdetails=1`,
                {
                    headers: {
                        'Accept': 'application/json'
                    }
                }
            );

            if (!res.ok) throw new Error('Reverse geocode failed');

            const data = await res.json();
            const addressParts = data.address || {};

            const city =
                addressParts.city ||
                addressParts.town ||
                addressParts.state_district ||
                addressParts.county ||
                'Dhaka';

            const rawArea =
                addressParts.suburb ||
                addressParts.neighbourhood ||
                addressParts.quarter ||
                addressParts.road ||
                '';

            const area = this.inferDhakaArea(rawArea, data.display_name, lat, lng);

            return {
                address: data.display_name || `${lat.toFixed(5)}, ${lng.toFixed(5)}`,
                city,
                area,
                lat: Number(lat),
                lng: Number(lng),
                source
            };
        } catch (e) {
            const inferredArea = this.inferDhakaArea('', '', lat, lng);
            return {
                address: inferredArea
                    ? `${inferredArea}, Dhaka (${lat.toFixed(5)}, ${lng.toFixed(5)})`
                    : `${lat.toFixed(5)}, ${lng.toFixed(5)}`,
                city: 'Dhaka',
                area: inferredArea || 'Banani',
                lat: Number(lat),
                lng: Number(lng),
                source
            };
        }
    },

    /**
     * Forward geocode a search query anywhere in Bangladesh using OpenStreetMap Nominatim
     */
    async forwardGeocode(query) {
        if (!query || !query.trim()) return null;
        try {
            const q = query.toLowerCase().includes('bangladesh')
                ? query.trim()
                : `${query.trim()}, Bangladesh`;

            const res = await fetch(
                `${FORWARD_GEOCODE_ENDPOINT}?q=${encodeURIComponent(q)}&countrycodes=bd&format=json&limit=1&addressdetails=1`,
                {
                    headers: {
                        'Accept': 'application/json'
                    }
                }
            );

            if (!res.ok) throw new Error('Forward geocode failed');

            const data = await res.json();
            if (!Array.isArray(data) || data.length === 0) return null;

            const first = data[0];
            const lat = parseFloat(first.lat);
            const lng = parseFloat(first.lon);
            const addressParts = first.address || {};
            const city =
                addressParts.city ||
                addressParts.town ||
                addressParts.state_district ||
                'Dhaka';
            const rawArea =
                addressParts.suburb ||
                addressParts.neighbourhood ||
                addressParts.quarter ||
                addressParts.road ||
                '';
            const area = this.inferDhakaArea(rawArea, first.display_name, lat, lng);

            return {
                address: first.display_name || `${lat.toFixed(5)}, ${lng.toFixed(5)}`,
                city,
                area,
                lat,
                lng,
                source: 'map'
            };
        } catch (e) {
            return null;
        }
    },

    /**
     * Multi-result live place suggestions across Bangladesh (local catalog first + OSM Nominatim enrichment)
     */
    async searchPlaces(query, cityBias = '') {
        const clean = String(query || '').trim();
        if (!clean) {
            return MeramotLocationCatalog.getPlacesByCity(cityBias || 'Dhaka').slice(0, 10);
        }

        const localMatches = MeramotLocationCatalog.search(clean, cityBias, 8);
        if (clean.length < 2) {
            return localMatches;
        }

        try {
            const q = clean.toLowerCase().includes('bangladesh')
                ? clean
                : (cityBias && !clean.toLowerCase().includes(cityBias.toLowerCase())
                    ? `${clean}, ${cityBias}, Bangladesh`
                    : `${clean}, Bangladesh`);

            const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
            const timeout = controller ? setTimeout(() => controller.abort(), 3200) : null;

            const res = await fetch(
                `${FORWARD_GEOCODE_ENDPOINT}?q=${encodeURIComponent(q)}&countrycodes=bd&format=json&limit=5&addressdetails=1`,
                {
                    headers: { 'Accept': 'application/json' },
                    signal: controller ? controller.signal : undefined
                }
            );
            if (timeout) clearTimeout(timeout);

            if (!res.ok) return localMatches;
            const data = await res.json();
            if (!Array.isArray(data) || data.length === 0) return localMatches;

            const seen = new Set(localMatches.map((m) => `${m.city.toLowerCase()}|${m.area.toLowerCase()}`));
            const merged = [...localMatches];

            for (const row of data) {
                const lat = parseFloat(row.lat);
                const lng = parseFloat(row.lon);
                if (Number.isNaN(lat) || Number.isNaN(lng)) continue;

                const parts = row.address || {};
                const rawCity = parts.city || parts.town || parts.state_district || parts.county || cityBias || 'Dhaka';
                const city = MeramotLocationCatalog.findDistrict(rawCity);
                const rawArea =
                    parts.suburb ||
                    parts.neighbourhood ||
                    parts.quarter ||
                    parts.Commercial ||
                    parts.road ||
                    parts.village ||
                    parts.town ||
                    String(row.name || '').trim();
                const area = this.inferDhakaArea(rawArea, row.display_name, lat, lng) || rawArea || clean;
                const key = `${city.toLowerCase()}|${area.toLowerCase()}`;
                if (seen.has(key)) continue;
                seen.add(key);

                merged.push({
                    area,
                    city,
                    lat: Number(lat.toFixed(6)),
                    lng: Number(lng.toFixed(6)),
                    address: row.display_name || `${area}, ${city}`,
                    source: 'osm'
                });
            }

            return merged.slice(0, 12);
        } catch (e) {
            return localMatches;
        }
    },

    /**
     * Synchronize saved location to Laravel session so Quick-Match & delivery radius always reflect current city/pin
     */
    async syncLocationWithServer(loc, reloadOnCartOrCatalog = false) {
        if (!loc || typeof fetch === 'undefined') return;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const sig = JSON.stringify([
            loc.city || '',
            loc.area || '',
            typeof loc.lat === 'number' ? Number(loc.lat).toFixed(4) : '',
            typeof loc.lng === 'number' ? Number(loc.lng).toFixed(4) : ''
        ]);
        try {
            sessionStorage.setItem('meramot.syncedLocationSig', sig);
            const res = await fetch('/location/sync', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: JSON.stringify({
                    lat: typeof loc.lat === 'number' ? loc.lat : null,
                    lng: typeof loc.lng === 'number' ? loc.lng : null,
                    city: loc.city || 'Dhaka',
                    area: loc.area || '',
                    address: loc.address || ''
                })
            });
            if (res.ok && reloadOnCartOrCatalog) {
                const path = window.location.pathname || '';
                if (path === '/cart' || path === '/cart/checkout' || path.startsWith('/packages/catalog')) {
                    if (window.MeramotSoftNav) {
                        window.MeramotSoftNav.visit(window.location.href, { replace: true });
                    } else {
                        window.location.reload();
                    }
                }
            }
        } catch (e) {}
    },

    /**
     * Save confirmed location to localStorage, notify components, and sync to server session
     */
    saveLocation(nextLocation) {
        const normalized = {
            address: nextLocation.address ||
                (typeof nextLocation.lat === 'number' && typeof nextLocation.lng === 'number'
                    ? `${nextLocation.lat.toFixed(5)}, ${nextLocation.lng.toFixed(5)}`
                    : 'Pinned location'),
            city: nextLocation.city || 'Dhaka',
            area: nextLocation.area || '',
            lat: typeof nextLocation.lat === 'number' ? Number(nextLocation.lat) : null,
            lng: typeof nextLocation.lng === 'number' ? Number(nextLocation.lng) : null,
            source: nextLocation.source || 'map'
        };

        const eventDetail = nextLocation._skipUrlSync ? { ...normalized, _skipUrlSync: true } : normalized;
        localStorage.setItem(LOCATION_STORAGE_KEY, JSON.stringify(normalized));
        window.dispatchEvent(new CustomEvent('meramot-location-changed', { detail: eventDetail }));
        this.syncLocationWithServer(normalized, !nextLocation._skipUrlSync);
        return normalized;
    },

    /**
     * Mount or refresh the interactive coverage map instance inside #meramot-leaflet-map
     */
    async initOrRefreshMap(containerId = 'meramot-leaflet-map', callbacks = {}) {
        this.callbacks = callbacks;

        try {
            await this.ensureLeafletLoaded();
        } catch (err) {
            callbacks.onError?.('Unable to load the interactive map. Please check your internet connection.');
            return;
        }

        const container = document.getElementById(containerId);
        if (!container || typeof window.L === 'undefined') {
            callbacks.onError?.('Map container could not be initialized.');
            return;
        }

        const L = window.L;
        const stored = this.parseStoredLocation();
        const initial = this.draftLocation || stored;
        const startLat = typeof initial?.lat === 'number' ? initial.lat : DEFAULT_COORDS[0];
        const startLng = typeof initial?.lng === 'number' ? initial.lng : DEFAULT_COORDS[1];
        const startZoom = (typeof initial?.lat === 'number' && typeof initial?.lng === 'number') ? 14 : 12;

        if (!this.mapInstance) {
            const map = L.map(container, {
                center: [startLat, startLng],
                zoom: startZoom,
                zoomControl: true
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            const pinIcon = L.divIcon({
                className: '',
                html: `<div style="position:relative;width:28px;height:38px;">
                    <div style="position:absolute;top:0;left:50%;width:28px;height:28px;background:#244229;border:3px solid #ffffff;border-radius:999px;transform:translateX(-50%);box-shadow:0 4px 10px rgba(15,23,42,.28);"></div>
                    <div style="position:absolute;bottom:1px;left:50%;width:0;height:0;border-left:8px solid transparent;border-right:8px solid transparent;border-top:12px solid #244229;transform:translateX(-50%);"></div>
                </div>`,
                iconSize: [28, 38],
                iconAnchor: [14, 38]
            });

            const marker = L.marker([startLat, startLng], {
                icon: pinIcon,
                draggable: true
            }).addTo(map);

            const handleCoordinatePick = async (lat, lng, source = 'map') => {
                marker.setLatLng([lat, lng]);
                this.callbacks.onLoading?.(true);
                const resolved = await this.reverseGeocode(lat, lng, source);
                this.draftLocation = resolved;
                this.callbacks.onDraftChange?.(resolved);
                this.callbacks.onLoading?.(false);
            };

            map.on('click', (event) => {
                handleCoordinatePick(event.latlng.lat, event.latlng.lng, 'map');
            });

            marker.on('dragend', () => {
                const pos = marker.getLatLng();
                handleCoordinatePick(pos.lat, pos.lng, 'map');
            });

            this.mapInstance = map;
            this.markerInstance = marker;

            if (typeof ResizeObserver !== 'undefined') {
                this.resizeObserver = new ResizeObserver(() => {
                    if (this.mapInstance) {
                        this.mapInstance.invalidateSize();
                    }
                });
                this.resizeObserver.observe(container);
            }
        } else {
            this.mapInstance.setView([startLat, startLng], startZoom);
            if (this.markerInstance) {
                this.markerInstance.setLatLng([startLat, startLng]);
            }
        }

        [40, 160, 350].forEach((delay) => {
            setTimeout(() => {
                if (this.mapInstance) {
                    this.mapInstance.invalidateSize();
                }
            }, delay);
        });
    },

    /**
     * Fly map & marker to specific coordinates
     */
    async flyToLocation(lat, lng, zoom = 15) {
        if (!this.mapInstance) {
            await this.initOrRefreshMap('meramot-leaflet-map', this.callbacks);
        }
        if (!this.mapInstance) return;
        this.mapInstance.invalidateSize();
        this.mapInstance.flyTo([lat, lng], zoom, { duration: 0.6 });
        if (this.markerInstance) {
            this.markerInstance.setLatLng([lat, lng]);
        }
    }
};

/* ============================================================================
 * DOM Ready Bindings (Distance Badges, Dynamic ETA & Distance Sort Sync)
 * ============================================================================ */
function renderShopDistanceBadges(userLat, userLng) {
    if (typeof userLat !== 'number' || typeof userLng !== 'number' || isNaN(userLat) || isNaN(userLng)) {
        return;
    }

    const cards = Array.from(document.querySelectorAll('[data-shop-lat][data-shop-lng]'));
    cards.forEach((shopCard) => {
        const shopLat = parseFloat(shopCard.dataset.shopLat);
        const shopLng = parseFloat(shopCard.dataset.shopLng);

        if (!isNaN(shopLat) && !isNaN(shopLng)) {
            const distance = MeramotGeolocation.calculateDistance(userLat, userLng, shopLat, shopLng);
            shopCard.dataset.computedDistanceKm = String(distance);

            const distanceEl = shopCard.querySelector('.distance-display');
            if (distanceEl) {
                distanceEl.textContent = MeramotGeolocation.formatDistance(distance);
                distanceEl.classList.remove('hidden');
            }

            const etaMinutes = MeramotGeolocation.estimateTravelTime(distance);
            const etaFormatted = MeramotGeolocation.formatEta(etaMinutes);
            shopCard.querySelectorAll('.eta-display').forEach((etaEl) => {
                etaEl.textContent = etaFormatted;
            });
        }
    });

    // Inject user's pinned coordinates into Nearest Distance sort links so server SQL sorts from exact pin
    document.querySelectorAll('a[data-sort-link="distance"]').forEach((link) => {
        try {
            const u = new URL(link.href, window.location.origin);
            u.searchParams.set('lat', userLat.toFixed(5));
            u.searchParams.set('lng', userLng.toFixed(5));
            link.href = u.pathname + '?' + u.searchParams.toString();
        } catch (e) {}
    });

    // Ensure search forms preserve lat/lng when sorting by distance
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('sort') === 'distance') {
        document.querySelectorAll('form[action="/shops"]').forEach((form) => {
            ['lat', 'lng', 'sort'].forEach((field) => {
                let input = form.querySelector(`input[name="${field}"]`);
                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = field;
                    form.appendChild(input);
                }
                if (field === 'lat') input.value = userLat.toFixed(5);
                if (field === 'lng') input.value = userLng.toFixed(5);
                if (field === 'sort') input.value = 'distance';
            });
        });

        // If the page loaded with sort=distance, ensure visible cards are ordered strictly ascending by distance
        if (cards.length > 1 && cards[0].parentElement) {
            const parent = cards[0].parentElement;
            const sorted = [...cards].sort((a, b) => {
                const da = parseFloat(a.dataset.computedDistanceKm ?? '999999');
                const db = parseFloat(b.dataset.computedDistanceKm ?? '999999');
                return da - db;
            });
            sorted.forEach((card) => parent.appendChild(card));
        }
    }
}

function refreshShopDistanceState() {
    const urlParams = new URLSearchParams(window.location.search);
    const stored = MeramotLocationPicker.parseStoredLocation();

    if (stored) {
        const sig = JSON.stringify([
            stored.city || '',
            stored.area || '',
            typeof stored.lat === 'number' ? Number(stored.lat).toFixed(4) : '',
            typeof stored.lng === 'number' ? Number(stored.lng).toFixed(4) : ''
        ]);
        if (sessionStorage.getItem('meramot.syncedLocationSig') !== sig) {
            const path = window.location.pathname || '';
            const shouldReload = path === '/cart' || path === '/cart/checkout' || path.startsWith('/packages/catalog');
            MeramotLocationPicker.syncLocationWithServer(stored, shouldReload);
        }
    }

    if (urlParams.has('lat') && urlParams.has('lng')) {
        renderShopDistanceBadges(parseFloat(urlParams.get('lat')), parseFloat(urlParams.get('lng')));
    } else if (stored && typeof stored.lat === 'number' && typeof stored.lng === 'number') {
        // If user selected sort=distance or maxDistanceKm without lat/lng in URL, sync URL with their pinned coordinates
        if ((urlParams.get('sort') === 'distance' || urlParams.has('maxDistanceKm')) && window.location.pathname.startsWith('/shops')) {
            const nextUrl = new URL(window.location.href);
            nextUrl.searchParams.set('lat', stored.lat.toFixed(5));
            nextUrl.searchParams.set('lng', stored.lng.toFixed(5));
            if (window.MeramotSoftNav) {
                window.MeramotSoftNav.visit(nextUrl.toString(), { replace: true });
            } else {
                window.location.replace(nextUrl.toString());
            }
            return;
        }
        renderShopDistanceBadges(stored.lat, stored.lng);
    }
}

MeramotGeolocation.refreshPageBindings = refreshShopDistanceState;

document.addEventListener('DOMContentLoaded', function () {
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('#use-my-location-btn, [data-detect-location]');
        if (!btn) return;
        e.preventDefault();
        if (btn.dataset.locating === 'true') return;
        btn.dataset.locating = 'true';

        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1.5"></i><span>Locating...</span>';
        btn.disabled = true;

        MeramotGeolocation.enableDistanceFilter(btn).finally(() => {
            delete btn.dataset.locating;
            if (document.body.contains(btn)) {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }
        });
    });

    refreshShopDistanceState();

    window.addEventListener('meramot-location-changed', function (e) {
        const loc = e.detail;
        if (loc && typeof loc.lat === 'number' && typeof loc.lng === 'number') {
            if (loc._skipUrlSync) {
                renderShopDistanceBadges(loc.lat, loc.lng);
                return;
            }
            const currentParams = new URLSearchParams(window.location.search);
            const nextLat = loc.lat.toFixed(5);
            const nextLng = loc.lng.toFixed(5);
            if ((currentParams.get('sort') === 'distance' || currentParams.has('maxDistanceKm')) && window.location.pathname.startsWith('/shops')) {
                if (currentParams.get('lat') !== nextLat || currentParams.get('lng') !== nextLng) {
                    const nextUrl = new URL(window.location.href);
                    nextUrl.searchParams.set('lat', nextLat);
                    nextUrl.searchParams.set('lng', nextLng);
                    if (window.MeramotSoftNav) {
                        window.MeramotSoftNav.visit(nextUrl.toString());
                    } else {
                        window.location.href = nextUrl.toString();
                    }
                    return;
                }
            }
            renderShopDistanceBadges(loc.lat, loc.lng);
        }
    });
});

window.meramotLocationSelector = function (config = {}) {
    return {
        openSuggestions: false,
        searchResults: [],
        highlightedIndex: -1,
        searchingOsm: false,
        _debounceTimer: null,

        get districtOptions() {
            return MeramotLocationCatalog.getDistricts();
        },

        areaOptionsFor(city, currentArea = '') {
            return MeramotLocationCatalog.getPlacesByCity(city || 'Dhaka', currentArea);
        },

        updateTypeahead(query, cityBias = '') {
            const q = String(query || '').trim();
            if (!q) {
                this.searchResults = MeramotLocationCatalog.getPlacesByCity(cityBias || 'Dhaka').slice(0, 10);
                this.openSuggestions = true;
                this.highlightedIndex = -1;
                return;
            }
            this.searchResults = MeramotLocationCatalog.search(q, cityBias, 8);
            this.openSuggestions = true;
            this.highlightedIndex = this.searchResults.length > 0 ? 0 : -1;

            if (this._debounceTimer) clearTimeout(this._debounceTimer);
            if (q.length >= 3) {
                this._debounceTimer = setTimeout(async () => {
                    this.searchingOsm = true;
                    const merged = await MeramotLocationPicker.searchPlaces(q, cityBias);
                    if (Array.isArray(merged) && merged.length > 0) {
                        this.searchResults = merged;
                    }
                    this.searchingOsm = false;
                }, 260);
            }
        },

        closeSuggestionsLater() {
            setTimeout(() => {
                this.openSuggestions = false;
            }, 180);
        }
    };
};

window.MeramotLocationCatalog = MeramotLocationCatalog;
window.MeramotGeolocation = MeramotGeolocation;
window.MeramotLocationPicker = MeramotLocationPicker;
