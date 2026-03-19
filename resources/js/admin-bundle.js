import * as THREE from 'three';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { TransformControls } from 'three/examples/jsm/controls/TransformControls.js';

window.THREE = THREE;
window.GLTFLoader = GLTFLoader;
window.OrbitControls = OrbitControls;
window.TransformControls = TransformControls;

// Import WebSockets exclusively for the backend
import './echo';
