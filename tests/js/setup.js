/* global jest */
import '@testing-library/jest-dom';

// jsdom does not implement scrolling; focus management and a11y.speak() call it.
window.scrollTo = jest.fn();
